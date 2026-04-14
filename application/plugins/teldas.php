<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * .
 *
 * @package  Application
 * @subpackage Plugins
 * @since    5.8
 */
class teldasPlugin extends Billrun_Plugin_BillrunPluginBase {

  protected $teldasUser;
  protected $teldasUrl;
  protected $teldasPassword;
  protected $teldasAccessToken;
  protected $cache;
  protected $matchingPathsByType;
  protected $moreSelctiveQuery = array();

  const RESPONSE_STATUS_OK = 200;
  const INVALID_TOKEN_ERROR = "invalid_token";
  const ACCESS_TOKEN_CACHE_KEY = "teldas_access_token";

  public function __construct($options = array()) {		
    $this->cache = Billrun_Factory::cache();
    $this->teldasUrl = Billrun_Util::getIn($options, 'url', 'https://ws.numberportability.ch');
    $this->teldasUser = Billrun_Util::getIn($options, 'user', '');
    $this->teldasPassword = Billrun_Util::getIn($options, 'password', '');
    $matchingPaths = Billrun_Util::getIn($options, 'matching_paths', '');
    foreach($matchingPaths as $matchingPathsConf){
        $this->matchingPathsByType[$matchingPathsConf['line_type']] = $matchingPathsConf;
    }
    $this->teldasAccessToken = !empty($this->cache) ? ($this->cache->get(self::ACCESS_TOKEN_CACHE_KEY) ?? '' ) : '';
    $this->options = $options;
    $this->nonWorkingDaysCollection = Billrun_Factory::db()->plugin_teldas_non_working_daysCollection(['force' => true]);
    $this->inaNumbersCollection = Billrun_Factory::db()->plugin_teldas_ina_numbersCollection(['force' => true]);
    $this->tariffsProfilesCollection = Billrun_Factory::db()->plugin_teldas_tariffs_profilesCollection(['force' => true]);
    $this->tariffSwitchingClassesCollection = Billrun_Factory::db()->plugin_teldas_tariff_switching_classesCollection(['force' => true]);
  }
    
  protected function authentication() {
    Billrun_Factory::log("Sending authentication request to teldas.", Zend_Log::DEBUG);
    $authenticationUrl = $this->teldasUrl . '/auth/login';
    $data = array("username" => $this->teldasUser, "password" => $this->teldasPassword);
    $result = json_decode(Billrun_Util::sendRequest($authenticationUrl, json_encode($data), Zend_Http_Client::POST, array('Content-Type: application/json')), true);
    if (empty($result['access_token'])) {
        Billrun_Factory::log("Failed to authenticate teldas. Error: " . $result['title'], Zend_Log::ALERT);
        return false;
    }
    Billrun_Factory::log("Teldas authentication Succeeded.", Zend_Log::DEBUG);
    $this->teldasAccessToken = $result['access_token'];
    if ($this->cache) {//todo:: verify this is working - still not checked
        $lifetime = Billrun_Factory::config()->getConfigValue('teldas.access_token.cache_lifetime', 43200); //12 hours
        $this->cache->set(self::ACCESS_TOKEN_CACHE_KEY, $this->teldasAccessToken, null, $lifetime);
    }
    return true;
  }

  public function cronHour() {
    if(date_default_timezone_get() != 'Europe/Zurich'){
        Billrun_Factory::log("To use Teldas plugin must have Europe/Zurich timezone.", Zend_Log::ALERT);
        return;
    }
    $houresToRun = Billrun_Util::getIn($this->options, 'cron_hours', []);  //if empty run every hour 
    if(empty($houresToRun)){
      $this->syncSystem();
    }else{
      $currentHour = date('G');
      foreach ($houresToRun as $hourToRun) {
        if ($currentHour == $hourToRun) {
         $this->syncSystem();
        }
      }
    }
  }

  protected function syncSystem(){
    if (!$this->isSystemInitialize()) {
      $this->initializeSystem();
    } else {
        $this->keepSystemUpToDate();
    }
  }

  public function cronDay() {
      if(date_default_timezone_get() != 'Europe/Zurich'){
        Billrun_Factory::log("To use Teldas plugin must have Europe/Zurich timezone.", Zend_Log::ALERT);
        return;
      }
      $this->importHolidays();
      $success = $this->keepSystemUpToDateOfTariffSwitchingClasses();
      if (!$success) {
          Billrun_Factory::log("Failed to keep system up to date of tariffs switching classes", Zend_Log::ALERT);
      }
  }

  protected function importHolidays() {
      Billrun_Factory::log("importing Holidays", Zend_Log::INFO);
      if (empty($this->teldasAccessToken)) {
          $success = $this->authentication();
          if (!$success) {
              Billrun_Factory::log("Importing Holidays failed", Zend_Log::ALERT);
              return;
          }
      }
      $year = date("Y");
      $nonWorkingDays = $this->getNonWorkingDays($year);
      $importingNonWorkingDays = [];
      foreach ($nonWorkingDays as &$nonWorkingDay) {
          $exclude = array_merge(Billrun_Factory::config()->getConfigValue('teldas.non_working_days.exclude', array()), array('Weekend'));
          if (in_array($nonWorkingDay['description'], $exclude)) {
              continue;
          }
          $nonWorkingDay['date'] = new MongoDate(strtotime($nonWorkingDay['date']));
          $dateTo = new MongoDate(strtotime("+" . $nonWorkingDay['duration'], $nonWorkingDay["date"]->sec));
          $importingNonWorkingDays[] = array_merge($nonWorkingDay, array('dateTo' => $dateTo));
      }
      $newestNonWorkingDays = $this->nonWorkingDaysCollection->query()->cursor()->sort(array('_id' => -1))->limit(1)->current()->getRawData();

      $result = $this->batchInsert($this->nonWorkingDaysCollection, $importingNonWorkingDays, "non working days");
      if(!$result){
        return;
      } 
      if(!empty($newestNonWorkingDays) ){
          $this->nonWorkingDaysCollection->remove(array('_id' => array('$lte' => $newestNonWorkingDays['_id'])));
      }

  }

  protected function sendRequestToTeldas($url, $parameters = array(), $type = Zend_Http_Client::GET){
      return json_decode(Billrun_Util::sendRequest($url, $parameters, $type, array('Content-Type: application/json', 'Authorization: Bearer ' . $this->teldasAccessToken)), true);
  }

  protected function getNonWorkingDays($year) {
      $url = $this->teldasUrl . '/inetonp/api/non-working-day';
      $parameters = array('year' => $year);
      Billrun_Factory::log("Sending request to get all non working days for a given year " . $year . "  from url: " . $url, Zend_Log::DEBUG);
      $result = $this->sendRequestToTeldas($url, $parameters);
      if (isset($result['error']) && $result['error'] === self::INVALID_TOKEN_ERROR) {
          Billrun_Factory::log("Get invalid token error. Reauthenticate and get a new token before resending a request", Zend_Log::DEBUG);
          if (!$this->authentication()) {
              return false;
          }
          $result = $this->sendRequestToTeldas($url, $parameters);
      }
      if (isset($result['errors'])) {
          $messages = array_map(function ($error) {
              return $error['message'];
          }, $result['errors']);
          Billrun_Factory::log("Failed to get all non working days with params: " . print_r($parameters, true) . ". Errors: " . print_r($messages, true), Zend_Log::ALERT);
          return false;
      } else if (isset($result['error'])) {
          Billrun_Factory::log("Failed to get all non working days with params: " . print_r($parameters, true) . ". Error: " . $result['error'], Zend_Log::ALERT);
          return false;
      } else if (is_null($result)) {
          Billrun_Factory::log("Failed to get all non working days with params: " . print_r($parameters, true), Zend_Log::ALERT);
          return false;
      } else {
          Billrun_Factory::log("Received " . count($result) . " non working days.", Zend_Log::DEBUG);
      }
      return $result;
  }

  protected function isSystemInitialize() {
      return Billrun_Factory::config()->getConfigValue('teldas.is_system_initialize', false);
  }

  protected function initializeSystem() {
      Billrun_Factory::log("Getting the complete list of INA numbers and tariffs from INet", Zend_Log::INFO);
      if (empty($this->teldasAccessToken)) {
          $success = $this->authentication();
          if (!$success) {
              Billrun_Factory::log("Initialize system failed", Zend_Log::ALERT);
              return;
          }
      }
      $parameters = array('transactionDateTimeFrom' => $this->getDateFormat(strtotime("-1 year")), 'transactionDateTimeTo' => $this->getDateFormat(strtotime("-1 second")));
      $success1 = $this->getCompleteListOfInaNumbers($parameters);
      if (!$success1) {
          Billrun_Factory::log("Failed to get the complete list of INA numbers", Zend_Log::ALERT);
          $this->clearTeldasCollections();
          return; 
      }
      $updateOnlineTariffProfile = Billrun_Util::getIn($this->options, 'update_online');
      $success2 = true;
      if($updateOnlineTariffProfile == true){
        $type="online";
        $success2 = $this->getCompleteListOfTariffsProfiles($type);
        if (!$success2) {
            Billrun_Factory::log("Failed to get the complete list of  $type tariffs profiles", Zend_Log::ALERT);
            $this->clearTeldasCollections();
            return; 
        }
      }
      
      $success3 = $this->getCompleteListOfTariffSwitchingClasses();
      if (!$success3) {
          Billrun_Factory::log("Failed to get the complete list of tariffs switching classes", Zend_Log::ALERT);
          $this->clearTeldasCollections();
          return;       }

      $updateOfflineATariffProfile = Billrun_Util::getIn($this->options, 'update_offline-a');
      $success4 = true;
      if($updateOfflineATariffProfile == true){
        $type="offline-a";
        $success4 = $this->getCompleteListOfTariffsProfiles($type);
        if (!$success4) {
            Billrun_Factory::log("Failed to get the complete list of $type tariffs profiles", Zend_Log::ALERT);
            $this->clearTeldasCollections();
            return;         }
      }
      
      if ($success1 && $success2 && $success3 && $success4) { //todo::remove this if 
          Billrun_Factory::log("Initialize system succeeded", Zend_Log::INFO);
          $this->updateConfigTeldasData('is_system_initialize', true);
          $this->updateConfigTeldasData('last_update_time', new MongoDate(strtotime($parameters['transactionDateTimeTo'])));
      }
  }
    protected function clearTeldasCollections(){
        Billrun_Factory::log("Clearing teldas collections", Zend_Log::INFO);
        $this->inaNumbersCollection->remove(array('_id'=> ['$exists' => true]));
        $this->tariffsProfilesCollection->remove(array('_id'=> ['$exists' => true]));
        $this->tariffSwitchingClassesCollection->remove(array('_id'=> ['$exists' => true]));
    }


  protected function updateConfigTeldasData($field, $value) {
      Billrun_Factory::log("Updating teldas." . $field . " in config to value: " . $value, Zend_Log::DEBUG);
      $model = new ConfigModel();
      $updatedData = $model->getConfig();
      unset($updatedData['_id']);
      $updatedData['teldas'][$field] = $value;
      $ret = Billrun_Factory::db()->configCollection(['force' => true])->insert($updatedData);
      $saveResult = !empty($ret['ok']);
      if ($saveResult) {
          // Reload timezone.
          Billrun_Config::getInstance()->refresh();
          Billrun_Factory::log("Succeeded to update teldas." . $field . " in config to value: " . $value, Zend_Log::DEBUG);
          return;
      }
      Billrun_Factory::log("Failed to update teldas." . $field . " in config to value: " . $value, Zend_Log::ALERT);
      return;
  }

  protected function getCompleteListOfInaNumbers($parameters) {
      Billrun_Factory::log("Getting the complete list of INA numbers", Zend_Log::DEBUG);
      $InaNumbersRecords = $this->getNumberOfAllInaNumbersRecords();
      if ($InaNumbersRecords === false) {
          return false;
      }
      $totalInaNumbers = [];
      $totalHistoryInaNumbers = [];
      $modifyPendingRevisions = [];
      $iteration = 0;
      $initializeLimit = Billrun_Util::getIn($this->options, 'initialize_years.limit', 30);
      while (count($totalInaNumbers) < $InaNumbersRecords) {
          $inaNumbers = $this->getInaNumbers($parameters);
          if ($inaNumbers === false) {
              return false;
          }
          $i = 1;
          foreach ($inaNumbers as $inaNumber) {               
              Billrun_Factory::log("Iteration: " . $i . " from " . count($inaNumbers), Zend_Log::DEBUG);
              $inaNumber['transactionDatetime'] = new MongoDate(strtotime($inaNumber['transactionDatetime']));
              $inaNumber['transactionDatetimeTo'] = null; //infinite               
              $i++;               
              if($inaNumber['modifyPending'] === true){
                  //handle modifyPending= true in initialize
                  $modifyPendingRevision = $this->handleModifyPending($inaNumber);
                  if($modifyPendingRevision === false){
                      return false;
                  }
                  $modifyPendingRevisions[] = $modifyPendingRevision;
              }
              $totalInaNumbers[] = $inaNumber;
              $historyBackLimit = strtotime(Billrun_Factory::config()->getConfigValue('teldas.initialize.ina_numbers_history.limit', "-1 month"));              
              if ($inaNumber['transactionDatetime']->sec < $historyBackLimit) {
                  continue;
              }
              $modifyPendingFound = false;
              $inaNumberHistory = $this->getInaNumberHistory($inaNumber['subscriberNumber'], $historyBackLimit, $modifyPendingFound, false, true);
              if($inaNumberHistory === false){
                  return false;
              }
              $totalHistoryInaNumbers = array_merge($totalHistoryInaNumbers, $inaNumberHistory);               
          }
          $parameters = array(
            'transactionDateTimeTo' => $parameters['transactionDateTimeFrom'],
            'transactionDateTimeFrom' => $this->getDateFormat(strtotime("-1 year", strtotime($parameters['transactionDateTimeFrom']))),
          );
          $iteration++;
          if ($iteration === $initializeLimit) {
            $countTotalInaNumbers = count($totalInaNumbers);
            Billrun_Factory::log("Should found $InaNumbersRecords records but found only " .$countTotalInaNumbers . " in " . $initializeLimit ." years", Zend_Log::NOTICE);
            $allowMistakeError = Billrun_Factory::config()->getConfigValue('teldas.initialize.allow_mistake_error', 0.0001);
            if(Billrun_Util::isEqual($countTotalInaNumbers/$InaNumbersRecords, 1, $allowMistakeError)){
                break;
            }
              return false;
          }
      }
      $totalInaNumbers = array_unique($totalInaNumbers, SORT_REGULAR);
      $result = $this->batchInsert($this->inaNumbersCollection, $totalInaNumbers, "INA numbers");
      if(!$result){
        return false;
      }
      $result = $this->batchInsert($this->inaNumbersCollection, $totalHistoryInaNumbers, "history INA numbers");
      if(!$result){
        return false;
      }

        $result = $this->batchInsert($this->inaNumbersCollection, $modifyPendingRevisions, "modify pending INA numbers");
        if(!$result){
            return false;
        }
 
      return true;
  }

  protected function getCompleteListOfTariffsProfiles($type) {
      Billrun_Factory::log("Getting the complete list of $type tariffs profiles", Zend_Log::DEBUG);
      $tariffsProfiles = $this->getTariffsProfiles([], $type);
      if ($tariffsProfiles === false) {
          return false;
      }
      foreach ($tariffsProfiles as &$tariffsProfile) {
          $tariffsProfile['transactionDateTime'] = new MongoDate(strtotime($tariffsProfile['transactionDateTime']));
          $tariffsProfile['transactionDateTimeTo'] = null;
      }
      $result = $this->batchInsert($this->tariffsProfilesCollection, $tariffsProfiles, "$type tariffs profiles");
      if(!$result){
          return false;
      }
      return true;
  }

  protected function getCompleteListOfTariffSwitchingClasses() {
      Billrun_Factory::log("Getting the complete list of tariff switching classes", Zend_Log::DEBUG);
      $tariffsSwitchingClasses = $this->getTariffSwitchingClasses();
      if ($tariffsSwitchingClasses === false) {
          return false;
      }
      foreach ($tariffsSwitchingClasses as &$tariffsSwitchingClass) {
          $tariffsSwitchingClass['transactionDateTime'] = new MongoDate(strtotime($tariffsSwitchingClass['transactionDateTime']));
          $tariffsSwitchingClass['transactionDateTimeTo'] = null;
      }
      $result = $this->batchInsert($this->tariffSwitchingClassesCollection, $tariffsSwitchingClasses, "tariffs switching classes");
      if(!$result){
          return false;
      }
      return true;
  }

  protected function keepSystemUpToDate() {
      Billrun_Factory::log("Keeping system up-to-date of INA numbers and tariffs from INet", Zend_Log::INFO);
      if (empty($this->teldasAccessToken)) {
          $success = $this->authentication();
          if (!$success) {
              Billrun_Factory::log("Keep system up to date failed", Zend_Log::ALERT);
              return;
          }
      }
      $lastUpdateTime = Billrun_Factory::config()->getConfigValue('teldas.last_update_time');
      if (empty($lastUpdateTime)) {
          Billrun_Factory::log("Keep system up to date failed. Last update time must be configure.", Zend_Log::ALERT);
          return;
      }
      $parameters = array(
          'transactionDateTimeFrom' => $this->getDateFormat($lastUpdateTime->sec + 1),
          'transactionDateTimeTo' => $this->getDateFormat(strtotime("-1 second"))
      );
      $missingPeriod = Billrun_Factory::config()->getConfigValue('teldas.keep_system_up_to_date.missing_period', 3600); //1 hour
      if (($diff = strtotime($parameters['transactionDateTimeTo']) - strtotime($parameters['transactionDateTimeFrom'])) > $missingPeriod) {                          
          $success1 = $this->insertMissingInaNumbersRevisions($parameters);
      } else {
          $success1 = $this->keepSystemUpToDateOfInaNumbers($parameters);
      }
      if (!$success1) {
          Billrun_Factory::log("Failed to keep system up to date of INA numbers", Zend_Log::ALERT);
          $this->revertTeldasCollections($lastUpdateTime);
          return;
      }
      $updateOnlineTariffProfile = Billrun_Util::getIn($this->options, 'update_online');
      $success2 = true;
      if($updateOnlineTariffProfile == true){
        $type = "online";
        $success2 = $this->keepSystemUpToDateOfTariffsProfiles($parameters, $type);
        if (!$success2) {
            Billrun_Factory::log("Failed to keep system up to date of $type tariffs profiles", Zend_Log::ALERT);
            $this->revertTeldasCollections($lastUpdateTime);
            return;
        }
      }
      $updateOfflineATariffProfile = Billrun_Util::getIn($this->options, 'update_offline-a');
      $success3 = true;
      if($updateOfflineATariffProfile == true){
        $type = "offline-a";
        $success3 = $this->keepSystemUpToDateOfTariffsProfiles($parameters, $type);
        if (!$success3) {
            Billrun_Factory::log("Failed to keep system up to date of $type tariffs profiles", Zend_Log::ALERT);
            $this->revertTeldasCollections($lastUpdateTime);
            return;        
        }
      }
    
      if ($success1 && $success2 && $success3) {//todo:: remove this if 
          Billrun_Factory::log("Keep system up to date succeeded", Zend_Log::INFO);
          $this->updateConfigTeldasData('last_update_time', new MongoDate(strtotime($parameters['transactionDateTimeTo'])));
      }
  }

  protected function revertTeldasCollections($lastUpdateTime){
    Billrun_Factory::log("Reverting all changes of teldas collection until last update time " .  $lastUpdateTime, Zend_Log::DEBUG);
    $query = array(
        'transactionDatetime' => array (
            '$gt' => $lastUpdateTime
        )
    );
    $ret = $this->inaNumbersCollection->remove($query);
    Billrun_Factory::log("Removed " . $ret['n'] ." INA numbers", Zend_Log::DEBUG);
    $ret = $this->tariffsProfilesCollection->remove($query);
    Billrun_Factory::log("Removed " . $ret['n'] ." tariffs profiles", Zend_Log::DEBUG);
    $ret = $this->tariffSwitchingClassesCollection->remove($query);
    Billrun_Factory::log("Removed " . $ret['n'] ." tariffs switching classes", Zend_Log::DEBUG);
    $mappingUpdates = [
        ['collection' =>  $this->inaNumbersCollection, 'sortField' => 'subscriberNumber'],
        ['collection' =>  $this->tariffsProfilesCollection, 'sortField' => 'id'],
        ['collection' =>  $this->tariffSwitchingClassesCollection, 'sortField' => 'id']
    ];
    foreach($mappingUpdates as $map){
        $collection = $map['collection'];
        // Step 1: Find the latest document _id per sortField
        $pipeline = [
            ['$sort' => [$map['sortField'] => 1, 'transactionDatetime' => -1]],
            [
                '$group' => [
                    '_id' => '$' . $map['sortField'],
                    'latestId' => ['$first' => '$_id'],
                    'transactionDatetimeTo' => ['$first' => '$transactionDatetimeTo'],
                ]
            ],
            [
                '$match' => ['transactionDatetimeTo' => ['$ne' => null]]
            ]

        ];
        
        $cursor = $collection->aggregate($pipeline);
    
        $latestIds = [];
        $latestIdsCount = 0;
        foreach ($cursor as $doc) {
            $latestIdsCount++;
            $latestIds[] = $doc['latestId'];
        }
        Billrun_Factory::log("Updating " . $latestIdsCount . " teldas collection: " . $collection->getName() .",  transactionDatetimeTo to null." , Zend_Log::DEBUG);

        // Step 2: Update only those latest documents
        if (!empty($latestIds)) {
            $res = $collection->update(
                ['_id' => ['$in' => $latestIds]],
                ['$set' => ['transactionDatetimeTo' => null]]
            , ['multiple' => true]);

        }
    }
  }
    

  protected function keepSystemUpToDateOfInaNumbers($parameters) {
      Billrun_Factory::log("Keeping system up-to-date of INA numbers", Zend_Log::DEBUG);
      $inaNumbers = $this->getInaNumbers($parameters);
      $modifyPendingRevisions = [];
      $updatingInaNumbers = 0;
      if ($inaNumbers === false) {
          return false;
      }
      foreach ($inaNumbers as &$inaNumber) {
          $inaNumber['transactionDatetime'] = new MongoDate(strtotime($inaNumber['transactionDatetime']));
          $inaNumber['transactionDatetimeTo'] = null; //infinity
          if($inaNumber['modifyPending'] === true){
              //handle modifyPending= true in update
              $modifyPendingRevision = $this->handleModifyPending($inaNumber);
              if($modifyPendingRevision === false){
                  return false;
              }
              $modifyPendingRevisions[] = $modifyPendingRevision;
          } 
          $query = array('subscriberNumber' => $inaNumber['subscriberNumber'], 'transactionDatetimeTo' => null);
          $update = array('$set' => array('transactionDatetimeTo' => $inaNumber['transactionDatetime']));
          Billrun_Factory::log("Updating transactionDatetimeTo field of INA number with subscriberNumber: " . $inaNumber['subscriberNumber'] . "  of previous record ", Zend_Log::DEBUG);
          $ret = $this->inaNumbersCollection->update($query, $update);
          if ($ret['nModified'] > 1) {
              Billrun_Factory::log("Something wrong. Modified transactionDatetimeTo field to " . $ret['nModified'] . " revisions instead of one. query: " . print_r($query, 1), Zend_Log::ERR);
              false;
          }
          $updatingInaNumbers += $ret['nModified'];
      }
      Billrun_Factory::log("Update " . $updatingInaNumbers . " INA number previous record ", Zend_Log::DEBUG);

      $result = $this->batchInsert($this->inaNumbersCollection, $inaNumbers, "INA numbers");
      if(!$result){
          return false;
      }
        $result = $this->batchInsert($this->inaNumbersCollection, $modifyPendingRevisions, "modify pending INA numbers");
        if(!$result){
            return false;
        }
      return true;
  }

  protected function keepSystemUpToDateOfTariffsProfiles($parameters, $type) {
      Billrun_Factory::log("Keeping system up-to-date of $type tariffs profiles", Zend_Log::DEBUG);
      $tariffsProfiles = $this->getTariffsProfiles($parameters, $type);
      if ($tariffsProfiles === false) {
          return false;
      }
      $updatingTariffsProfiles = 0;
      foreach ($tariffsProfiles as &$tariffsProfile) {
          $tariffsProfile['transactionDateTime'] = new MongoDate(strtotime($tariffsProfile['transactionDateTime']));
          $tariffsProfile['transactionDateTimeTo'] = null;
          $query = array('id' => $tariffsProfile['id'], 'transactionDatetimeTo' => null);
          $update = array('$set' => array('transactionDateTimeTo' => $tariffsProfile['transactionDateTime']));
          Billrun_Factory::log("Updating transactionDatetimeTo field of $type tariffs profiles with id: " . $tariffsProfile['id'] . "  of previous record ", Zend_Log::DEBUG);
          $ret = $this->tariffsProfilesCollection->update($query, $update);
          if ($ret['nModified'] > 1) {
              Billrun_Factory::log("Something wrong. Modified transactionDatetimeTo field to " . $ret['nModified'] . " revisions instead of one. query: " . print_r($query, 1), Zend_Log::ERR);
              false;
          }
          $updatingTariffsProfiles += $ret['nModified'];
      }
      Billrun_Factory::log("Update " . $updatingTariffsProfiles . " $type tariffs profiles previous record ", Zend_Log::DEBUG);
      $result = $this->batchInsert($this->tariffsProfilesCollection, $tariffsProfiles, "$type tariffs profiles");
        if(!$result){
            return false;
        }
      return true;
  }


  
  protected function batchInsert($collection, $entitiesToInsert, $logTiltle){
    Billrun_Factory::log("Inserting " . count($entitiesToInsert) . " $logTiltle."  , Zend_Log::DEBUG);
    try {
        if(!empty($entitiesToInsert)){
            $ret = $collection->batchInsert($entitiesToInsert);
			if (isset($ret['err']) && !is_null($ret['err'])) {
                Billrun_Factory::log("Batch Insert of " . $logTiltle . " failed. Error: " . $ret['errmsg'], Zend_Log::ALERT);
				throw new Exception($ret['err'], $ret['errmsg']);
			}
        }
	} catch (Exception $e) {
        try {
            Billrun_Factory::log("Inserting " . $logTiltle . " line by line",  Zend_Log::DEBUG);

            foreach ($entitiesToInsert as $entity) {
                try {
                    $ret = $collection->insert($entity); // ok==1, err null
                    if (isset($ret['err']) && !is_null($ret['err'])) {
                        throw new Exception($ret['err'], $ret['errmsg']);
                    }
                } catch (Exception $e) {
                    if (in_array($e->getCode(), Mongodloid_General::DUPLICATE_UNIQUE_INDEX_ERROR)) {
                        Billrun_Factory::log("Insertion of " . $logTiltle . "  failed, Insert Error: " . $e->getMessage() , Zend_Log::NOTICE);
                        continue;
                    } else {
                        Billrun_Factory::log("Insertion of " . $logTiltle . "  failed, Insert Error: " . $e->getMessage() , Zend_Log::ALERT);
                        throw $e;
                    }
                }
            }
        } catch (Exception $ex) {
            Billrun_Factory::log("Inserting " . $logTiltle .  "failed. Error: " . $e->getMessage(), Zend_Log::ERR);
            return false;
        }
	}
    return true;
  }

  protected function keepSystemUpToDateOfTariffSwitchingClasses() {
      Billrun_Factory::log("Keeping system up-to-date of tariffs switching classes", Zend_Log::DEBUG);
      $tariffSwitchingClasses = $this->getTariffSwitchingClasses();
      if ($tariffSwitchingClasses === false) {
          return false;
      }
      foreach ($tariffSwitchingClasses as &$tariffSwitchingClass) {
          $tariffSwitchingClass['transactionDateTime'] = new MongoDate(strtotime($tariffSwitchingClass['transactionDateTime']));
          $tariffSwitchingClass['transactionDateTimeTo'] = null;
      }
      $newestTariffSwitchingClass = $this->tariffSwitchingClassesCollection->query()->cursor()->sort(array('_id' => -1))->limit(1)->current()->getRawData();
      $result = $this->batchInsert($this->tariffSwitchingClassesCollection, $tariffSwitchingClasses, "tariff switching classes");
      if(!$result){
          return false;
      }
      if(!empty($newestTariffSwitchingClass) ){
          $this->tariffSwitchingClassesCollection->remove(array('_id' => array('$lte' => $newestTariffSwitchingClass['_id'])));
      }
      Billrun_Factory::log("Succeeded to Keep system up-to-date of tariffs switching classes", Zend_Log::INFO);
      return true;
  }

  protected function getNumberOfAllInaNumbersRecords() {
      return $this->getInaNumbers(array(), true);
  }

  protected function getInaNumbers($parameters, $returnNumberOfRecords = false) {
      $url = $this->teldasUrl . '/inetina/api/number';
      Billrun_Factory::log("Sending request to get INA numbers from " . $url . " with params: " . print_r($parameters, true), Zend_Log::DEBUG);
      $result = $this->sendRequestToTeldas($url, $parameters);
      if (isset($result['error']) && $result['error'] === self::INVALID_TOKEN_ERROR) {
          Billrun_Factory::log("Get invalid token error. Reauthenticate and get a new token before resending a request", Zend_Log::DEBUG);
          if (!$this->authentication()) {
              return false;
          }
          $result = $this->sendRequestToTeldas($url, $parameters);
      }
      if (isset($result['errors']) && $returnNumberOfRecords) {
          foreach ($result['errors'] as $error) {
              if (isset($error['numberOfRecords'])) {
                  Billrun_Factory::log("The complete list of INA numbers has " . $error['numberOfRecords'] . " records.", Zend_Log::DEBUG);
                  return $error['numberOfRecords'];
              }
          }
          $messages = array_map(function ($error) {
              return $error['message'];
          }, $result['errors']);
          Billrun_Factory::log("Failed to get number of all INA numbers records. Errors: " . print_r($messages, true), Zend_Log::ALERT);
          return false;
      } else if (isset($result['errors']) && !$returnNumberOfRecords) {
          $messages = array_map(function ($error) {
              return $error['message'];
          }, $result['errors']);
          Billrun_Factory::log("Failed to get INA numbers with params: " . print_r($parameters, true) . ". Errors: " . print_r($messages, true), Zend_Log::ALERT);
          if($result['errors'][0]['messageId'] == 202){
            Billrun_Factory::log("Doing more selective query api. number Of Records: " . $result["errors"][0]["numberOfRecords"], Zend_Log::DEBUG);
            $selectiveResult =  $this->doMoreSelectiveQuery($parameters);
            if($selectiveResult === false){
                return false;
            }
            if($result["errors"][0]["numberOfRecords"] !== count($selectiveResult)){
                Billrun_Factory::log("Missing records. Need to have: " . $result["errors"][0]["numberOfRecords"] . " found only  " .  count($selectiveResult)  , Zend_Log::ALERT);
                return false;
            }
            return $selectiveResult;
          }
          return false;
      } else if (isset($result['error'])) {
          Billrun_Factory::log("Failed to get INA numbers with params: " . print_r($parameters, true) . ". Error: " . $result['error'], Zend_Log::ALERT);
          return false;
      } else if (is_null($result)) {
          Billrun_Factory::log("Failed to get INA numbers with params: " . print_r($parameters, true), Zend_Log::ALERT);
          return false;
      } else {
          Billrun_Factory::log("Received " . count($result) . " INA Numbers.", Zend_Log::DEBUG);
      }
      return $result;
  }

  protected function doMoreSelectiveQuery($parameters){
    $stamp =  Billrun_Util::generateArrayStamp($parameters);
    if(isset($this->moreSelctiveQuery[$stamp])){
        $res = $this->moreSelectiveQueryWithSubscriberNumber($parameters);
        if($res === false){
            return false;
        }
        return $res;
    }
    $this->moreSelctiveQuery[$stamp] = true;
    $endDateStr = $parameters["transactionDateTimeTo"];
    $startDateStr = $parameters["transactionDateTimeFrom"];
    $parameters["transactionDateTimeTo"] =  $this->getMiddleDatetimeWithMilliseconds($startDateStr, $endDateStr);
    $result1 = $this->getInaNumbers($parameters);
    if($result1 === false){
        return false;
    }
    $parameters["transactionDateTimeFrom"] = $parameters["transactionDateTimeTo"];
    $parameters["transactionDateTimeTo"] = $endDateStr;
    $result2 = $this->getInaNumbers($parameters);
    if($result2 === false){
        return false;
    }
    return array_merge($result2, $result1);
  }

  protected function moreSelectiveQueryWithSubscriberNumber($parameters){
    $selectiveResult = [];
    $subscriberNumberQueryParams = [
        [
            'from' => '0800000000',
            'to' => '0800999999'
        ],
        [
            'from' => '0840000000',
            'to' => '0849999999'
        ],
        [
            'from' => '0900000000',
            'to' => '0906999999'
        ],
        [
            'from' => '1800',
            'to' => '1899'
        ],

    ];

    foreach($subscriberNumberQueryParams as $param){
        $parameters['subscriberNumberFrom'] = $param['from'];
        $parameters['subscriberNumberTo'] = $param['to'];
        $result = $this->getInaNumbers($parameters);
        if($result === false){
            Billrun_Factory::log("Failed to do more selective query api with params: " . print_r($parameters, true), Zend_Log::ALERT);
            return false;
        }
        $selectiveResult = array_merge($selectiveResult, $result);
    }
    return $selectiveResult;
  }


  protected function getMiddleDatetimeWithMilliseconds($startDateStr, $endDateStr) {
    $start = new DateTime($startDateStr);
    $end = new DateTime($endDateStr);

    // Convert to float seconds including microtime
    $startTs = (float) $start->format('U.u');
    $endTs = (float) $end->format('U.u');

    // Midpoint as float
    $middleTs = ($startTs + $endTs) / 2;

    // Create DateTime from float seconds
    $middle = DateTime::createFromFormat('U.u', number_format($middleTs, 6, '.', ''));

    // Format with milliseconds (3 digits of microseconds)
    $formatted = $middle->format("Y-m-d\TH:i:s.") . substr($middle->format('u'), 0, 3);

    return $formatted;
}

  protected function getTariffsProfiles($parameters, $type) {
      $url = $this->teldasUrl . '/inetina/api/tariff/' . $type;
      Billrun_Factory::log("Sending request to get $type tariffs profiles from " . $url . " with params: " . print_r($parameters, true), Zend_Log::DEBUG);
      $result = $this->sendRequestToTeldas($url, $parameters);
      if (isset($result['error']) && $result['error'] === self::INVALID_TOKEN_ERROR) {
          Billrun_Factory::log("Get invalid token error. Reauthenticate and get a new token before resending a request", Zend_Log::DEBUG);
          if (!$this->authentication()) {
              return false;
          }
          $result = $this->sendRequestToTeldas($url, $parameters);
      }
      if (isset($result['errors'])) {
          $messages = array_map(function ($error) {
              return $error['message'];
          }, $result['errors']);
          Billrun_Factory::log("Failed to get $type tariffs profiles with params: " . print_r($parameters, true) . ". Errors: " . print_r($messages, true), Zend_Log::ALERT);
          return false;
      } else if (isset($result['error'])) {
          Billrun_Factory::log("Failed to get $type tariffs profiles with params: " . print_r($parameters, true) . ". Error: " . $result['error'], Zend_Log::ALERT);
          return false;
      } else if (is_null($result)) {
          Billrun_Factory::log("Failed to get $type tariffs profiles with params: " . print_r($parameters, true), Zend_Log::ALERT);
          return false;
      } else {
          Billrun_Factory::log("Received " . count($result) . " $type tariffs profiles.", Zend_Log::DEBUG);
      }
      return $result;
  }

  protected function getTariffSwitchingClasses() {
      $url = $this->teldasUrl . '/inetina/api/tariff-switching-class';
      Billrun_Factory::log("Sending request to get tariff switching classes from " . $url, Zend_Log::DEBUG);
      $result = $this->sendRequestToTeldas($url);
      if (isset($result['error']) && $result['error'] === self::INVALID_TOKEN_ERROR) {
          Billrun_Factory::log("Get invalid token error. Reauthenticate and get a new token before resending a request", Zend_Log::DEBUG);
          if (!$this->authentication()) {
              return false;
          }
          $result = $this->sendRequestToTeldas($url);
      }
      if (isset($result['errors'])) {
          $messages = array_map(function ($error) {
              return $error['message'];
          }, $result['errors']);
          Billrun_Factory::log("Failed to get tariff switching classes. Errors: " . print_r($messages, true), Zend_Log::ALERT);
          return false;
      } else if (isset($result['error'])) {
          Billrun_Factory::log("Failed to get tariff switching classes. Error: " . $result['error'], Zend_Log::ALERT);
          return false;
      } else if (is_null($result)) {
          Billrun_Factory::log("Failed to get tariff switching classes.", Zend_Log::ALERT);
          return false;
      } else {
          Billrun_Factory::log("Received " . count($result) . " tariff switching classes.", Zend_Log::DEBUG);
      }
      return $result;
  }

  protected function getMatchingInaNumberRevision($inaNumber, $urt) {
      $query = array('subscriberNumber' => $inaNumber, 'transactionDatetime' => array('$lte' => new MongoDate($urt)), '$or' => array(array('transactionDatetimeTo' => array('$gt' => new MongoDate($urt))), array('transactionDatetimeTo' => array('$eq' => null))));
      $inaNumberRevisions = $this->inaNumbersCollection->query($query)->cursor()->limit(1)->current();
      if ($inaNumberRevisions->isEmpty()) {
          Billrun_Factory::log("Not found matching subscriberNumber for Dest_Number in INA numbers collection. query: " . print_r($query), Zend_Log::NOTICE);
          return false;
      }
            return $inaNumberRevisions;//can be more then 1 but with the same info (future modify)
  }

  protected function getInaNumberHistory($subscriberNumber, $historyBackLimit, &$modifyPendingFound, $addFirst = true, $addPreviousBeforeLimit = false) {
      $result = $this->getInaNumberHistoryFromTeldas($subscriberNumber);
      if($result === false) {
          return false;
      }
      $prevTransactionDatetime = null;
      $inaNumberHistory = [];
      array_multisort(array_column($result, 'transactionDatetime'), SORT_DESC, $result);
      $first = true;
      foreach ($result as $inaNumberRevision) {
          if($inaNumberRevision['status'] === 'F_MOD'){
              $modifyPendingFound = true;               
              continue;
          }
          $inaNumberRevision['transactionDatetime'] = new MongoDate(strtotime($inaNumberRevision['transactionDatetime']));
          $inaNumberRevision['transactionDatetimeTo'] = $prevTransactionDatetime;
          unset($inaNumberRevision['userId']);
          if ($inaNumberRevision['transactionDatetime']->sec < $historyBackLimit) {
              if ($addPreviousBeforeLimit) {
                  $inaNumberHistory[] = $inaNumberRevision;
              }
              break;
          }
          if ($addFirst || !$first) {
              $inaNumberHistory[] = $inaNumberRevision;
          }
          $prevTransactionDatetime = $inaNumberRevision['transactionDatetime'];
          $first = false;
      }       
      return $inaNumberHistory;
  }

  protected function getInaNumberHistoryFromTeldas($subscriberNumber){
      Billrun_Factory::log("Getting  " . $subscriberNumber . " INA number history.", Zend_Log::DEBUG);
      $url = $this->teldasUrl . '/inetina/api/number/' . $subscriberNumber . "/history";
      Billrun_Factory::log("Sending request to get INA number history for " . $subscriberNumber . " from " . $url, Zend_Log::DEBUG);
      $result = $this->sendRequestToTeldas($url);
      if (isset($result['error']) && $result['error'] === self::INVALID_TOKEN_ERROR) {
          Billrun_Factory::log("Get invalid token error. Reauthenticate and get a new token before resending a request", Zend_Log::DEBUG);
          if (!$this->authentication()) {
              return false;
          }
          $result = $this->sendRequestToTeldas($url);
      }
      if (isset($result['errors'])) {
          $messages = array_map(function ($error) {
              return $error['message'];
          }, $result['errors']);
          Billrun_Factory::log("Failed to get INA number history for " . $subscriberNumber . ". Errors: " . print_r($messages, true), Zend_Log::ALERT);
          return false;
      } else if (isset($result['error'])) {
          Billrun_Factory::log("Failed to get INA number history for " . $subscriberNumber . ". Error: " . $result['error'], Zend_Log::ALERT);
          return false;
      } else if (is_null($result)) {
          Billrun_Factory::log("Failed to get INA number history for " . $subscriberNumber, Zend_Log::ALERT);
          return false;
      } else {
          Billrun_Factory::log("Received " . count($result) . " revisions for " . $subscriberNumber, Zend_Log::DEBUG);
      }
      return $result;
  }

  protected function insertMissingInaNumbersRevisions($parameters) {
      Billrun_Factory::log("Inserting Missing INA numbers revisions", Zend_Log::DEBUG);
      $inaNumbers = $this->getInaNumbers($parameters);
      if ($inaNumbers === false) {
          return false;
      }
      $missingInaNumbersRevisions = [];
      $modifyPendingRevisions = [] ; 
      $updatingInaNumbers = 0;
      foreach ($inaNumbers as $inaNumber) {
          $modifyPendingFound = false;
          $missingInaNumberRevisions = $this->getMissingInaNumberRevisions($inaNumber['subscriberNumber'], strtotime($parameters['transactionDateTimeFrom']), $modifyPendingFound);
          if ($missingInaNumberRevisions === false) {
              return false;
          }
          if($modifyPendingFound){
              $modifyPendingRevision = $this->handleModifyPending($missingInaNumberRevisions[0]);
              if($modifyPendingRevision === false){
                  return false;
              }
              $modifyPendingRevisions[] = $modifyPendingRevision;
          }
          $missingInaNumbersRevisions = array_merge($missingInaNumbersRevisions, $missingInaNumberRevisions);
          $oldestMissingInaNumberRevision = end($missingInaNumberRevisions);
          $query = array('subscriberNumber' => $oldestMissingInaNumberRevision['subscriberNumber'], 'transactionDatetimeTo' => null);
          $update = array('$set' => array('transactionDatetimeTo' => $oldestMissingInaNumberRevision['transactionDatetime']));
          Billrun_Factory::log("Updating transactionDatetimeTo field of INA number with subscriberNumber: " . $oldestMissingInaNumberRevision['subscriberNumber'] . "  of previous record ", Zend_Log::DEBUG);
          $ret = $this->inaNumbersCollection->update($query, $update);
          if ($ret['nModified'] > 1) {
              Billrun_Factory::log("Something wrong. Modified transactionDatetimeTo field to " . $ret['nModified'] . " revisions instead of one. query: " . print_r($query, 1), Zend_Log::ERR);
              false;
            }
            $updatingInaNumbers += $ret['nModified'] ?? 0;
      }
      Billrun_Factory::log("Update " . $updatingInaNumbers . " INA number previous record ", Zend_Log::DEBUG);
      $result = $this->batchInsert($this->inaNumbersCollection, $missingInaNumbersRevisions, "missing INA numbers");
      if(!$result){
        return false;
      }
      $result = $this->batchInsert($this->inaNumbersCollection, $modifyPendingRevisions, "modify pending INA numbers.");
      if(!$result){
        return false;
      }
      return true;
  }

  protected function getMissingInaNumberRevisions($subscriberNumber, $historyBackLimit, &$modifyPendingFound) {
      $missingInaNumberRevisions = $this->getInaNumberHistory($subscriberNumber, $historyBackLimit, $modifyPendingFound);
      if ($missingInaNumberRevisions === false) {
          return false;
      }
      return $missingInaNumberRevisions;
  }

  protected function handleModifyPending(&$inaNumber) {
      Billrun_Factory::log("Handle modify pending for INA number " .  $inaNumber['subscriberNumber'], Zend_Log::DEBUG);
      $inaNumberHistory = $this->getInaNumberHistoryFromTeldas($inaNumber['subscriberNumber']);
      if($inaNumberHistory === false){
          return false;
      }
      $modifyPendingRevision = $inaNumberHistory[0];
      if($modifyPendingRevision['status'] !== 'F_MOD'){
          Billrun_Factory::log("Something wrong. modify pending revision status need to be F_MOD" . print_r($modifyPendingRevision, 1), Zend_Log::ERR);
          return false;
      }
      $modifyPendingRevision['originalTransactionDatetime'] = $modifyPendingRevision['transactionDatetime'];
      $modifyPendingRevision['transactionDatetime'] = new MongoDate(strtotime($modifyPendingRevision['modificationDatetime']));
      $modifyPendingRevision['transactionDatetimeTo'] = null; //infinity
      $modifyPendingRevision['futureModify'] = true;
      Billrun_Factory::log("updating INA number " .  $inaNumber['subscriberNumber'], Zend_Log::DEBUG);
      $inaNumber['transactionDatetimeTo'] = new MongoDate(strtotime($modifyPendingRevision['modificationDatetime']));
      return $modifyPendingRevision;
  }

  protected function checkIfValidInaNumber($inaNumberRevison, $urt, $id) {
      if (is_null($inaNumberRevison)) {
          Billrun_Factory::log("Missing matching revision for " . $id . "INA number from " . date("Y-m-d H:i:s", $urt), Zend_Log::ALERT);
          return false;
      }
      if (empty($inaNumberRevison['tariffProfile'])) {
          Billrun_Factory::log("Matching INA number revision not have  tariffProfile. " . print_r($inaNumberRevison, 1), Zend_Log::ALERT);
          return false;
      }
      $activationDatetime = $inaNumberRevison['activationDatetime'] ? strtotime($inaNumberRevison['activationDatetime']) : null;
      if (is_null($activationDatetime)) {
          Billrun_Factory::log("Matching INA number revision not activated in " . date("Y-m-d H:i:s", $urt) . "." . print_r($inaNumberRevison, 1), Zend_Log::ALERT);
          return false;
      }

      $terminationDatetime = $inaNumberRevison['terminationDatetime'] ? strtotime($inaNumberRevison['terminationDatetime']) : strtotime("+150 years"); // if null then infinity
      if (!($urt < $terminationDatetime && $urt >= $activationDatetime)) {
          Billrun_Factory::log("Matching INA number revision not activated in " . date("Y-m-d H:i:s", $urt) . "." . print_r($inaNumberRevison, 1), Zend_Log::ALERT);
          return false;
      }
      return true;
  }

  protected function getMatchingTariffProfile($tariffProfileId, $urt) {
      $query = array('id' => $tariffProfileId, 'transactionDateTime' => array('$lte' => new MongoDate($urt)), '$or' => array(array('transactionDateTimeTo' => array('$gt' => new MongoDate($urt))), array('transactionDateTimeTo' => array('$eq' => null))));
      $tariffsProfilesRevisions = $this->tariffsProfilesCollection->query($query)->cursor();
      if ($tariffsProfilesRevisions->count() === 0) {
          Billrun_Factory::log("Failed to find matching tariff profile id. query: " . print_r($query, 1), Zend_Log::ALERT);
          return false;
      }
      if (($matchingRecords = $tariffsProfilesRevisions->count()) > 1) {
          Billrun_Factory::log("Something wrong. need to find only one matching online tariff profiles. found " . $matchingRecords . " matching INA number records." . print_r(iterator_to_array($tariffsProfilesRevisions)), Zend_Log::ERR);
          return false;
      }
      return $tariffsProfilesRevisions->current();
  }

  protected function getMatchingTariffSwitchingClass($tariffSwitchingClassId, $urt) {
      $query = array('id' => $tariffSwitchingClassId, 'transactionDateTime' => array('$lte' => new MongoDate($urt)), '$or' => array(array('transactionDateTimeTo' => array('$gt' => new MongoDate($urt))), array('transactionDateTimeTo' => array('$eq' => null))));
      $tariffSwitchingClassesRevisions = $this->tariffSwitchingClassesCollection->query($query)->cursor()->limit(1)->current();
      if ($tariffSwitchingClassesRevisions->isEmpty()) {
          Billrun_Factory::log("Failed to find matching tariff switching class id. query: " . print_r($query, 1), Zend_Log::ALERT);
          return false;
      }
      return $tariffSwitchingClassesRevisions;
  }

  protected function checkIfValidTariffProfile($tariffProfileRevision, $urt, $id) {
      if (is_null($tariffProfileRevision)) {
          Billrun_Factory::log("Missing matching revision for tariff profile with with id " . $id . " that match to urt: " . date("Y-m-d\TH:i:s.000\Z", $urt), Zend_Log::ALERT);
          return false;
      }
      if ($tariffProfileRevision['tariffProfileType'] === 'ONLINE' && empty($tariffProfileRevision['tariffSwitchingClassId'])) {
          Billrun_Factory::log("Matching online tariff profile revision not have tariff Switching Class Id. " . print_r($tariffProfileRevision, 1), Zend_Log::ALERT);
          return false;
      }

      if ($tariffProfileRevision['tariffProfileType'] === 'ONLINE' && empty($tariffProfileRevision['chargeConfigurations'])) {
          Billrun_Factory::log("Matching online tariff profile revision not have charge configurations. " . print_r($tariffProfileRevision, 1), Zend_Log::ALERT);
          return false;
      }
      if($tariffProfileRevision['tariffProfileType'] === 'OFFLINE_A' && empty($tariffProfileRevision['weekChargeConfiguration'])) {
        Billrun_Factory::log("Matching offline-a tariff profile revision not have week charge configurations. " . print_r($tariffProfileRevision, 1), Zend_Log::ALERT);
        return false;
    }

      $validDateTimeFrom = strtotime($tariffProfileRevision['validDateTimeFrom']);
      $validDateTimeTo = $tariffProfileRevision['validDateTimeTo'] ? strtotime($tariffProfileRevision['validDateTimeTo']) : strtotime("+150 years"); // if null then infinity
      if (is_null($validDateTimeFrom)) {
          Billrun_Factory::log("Matching tariff profile revision not valid in " . date("Y-m-d H:i:s", $urt) . "." . print_r($tariffProfileRevision, 1), Zend_Log::ALERT);
          return false;
      }
      if (!($urt < $validDateTimeTo && $urt >= $validDateTimeFrom)) {
          Billrun_Factory::log("Matching tariff profile revision not valid in " . date("Y-m-d H:i:s", $urt) . "." . print_r($tariffProfileRevision, 1), Zend_Log::ALERT);
          return false;
      }
      return true;
  }

  protected function checkIfValidTariffSwitchingClassId($tariffSwitchingClassRevision, $urt, $id) {
      if (is_null($tariffSwitchingClassRevision)) {
          Billrun_Factory::log("Missing matching revision for switching class with with id " . $id . " that match to urt: " . date("Y-m-d H:i:s", $urt), Zend_Log::ALERT);
          return false;
      }

      $validDateTimeFrom = strtotime($tariffSwitchingClassRevision['validDateTimeFrom']);
      $validDateTimeTo = $tariffSwitchingClassRevision['validDateTimeTo'] ? strtotime($tariffSwitchingClassRevision['validDateTimeTo']) : strtotime("+150 years"); // if null then infinity
      if (is_null($validDateTimeFrom)) {
          Billrun_Factory::log("Matching tariff switching class revision not valid in " . date("Y-m-d H:i:s", $urt) . "." . print_r($tariffSwitchingClassRevision, 1), Zend_Log::ALERT);
          return false;
      }
      if (!($urt < $validDateTimeTo && $urt >= $validDateTimeFrom)) {
          Billrun_Factory::log("Matching tariff switching class revision not valid in " . date("Y-m-d H:i:s", $urt) . "." . print_r($tariffSwitchingClassRevision, 1), Zend_Log::ALERT);
          return false;
      }
      return true;
  }

  protected function  getDateFormat($timestamp, $format = "Y-m-d\TH:i:s.000"){
      return date($format, $timestamp);
  }

  protected function calcPriceByOnlineTariffProfileSequence($tariffProfile, $sequence, $line) {
      $matchingPaths = $this->matchingPathsByType[$line['type']] ?? null;
      $chargeConfigurations = $tariffProfile['chargeConfigurations'];
      $matchingChargeConfigurations = null;
      foreach ($chargeConfigurations as $chargeConfiguration) {
          if ($chargeConfiguration['sequence'] == $sequence) {
              $matchingChargeConfigurations = $chargeConfiguration;
              break;
          }
      }
      if (is_null($matchingChargeConfigurations)) {
          Billrun_Factory::log("Empty week charge configurations for sequence : " . $sequence . ". Tariff proffile revision: " . print_r($tariffProfile, 1), Zend_Log::ALERT);
          return false;
      }
      $durationPath = Billrun_Util::getIn($matchingPaths, 'duration.path');
      $duration = Billrun_Util::getIn($line, $durationPath);
      if (is_null($duration)) {
          Billrun_Factory::log("Failed to get " . $durationPath . "  from line." . print_r($line, 1), Zend_Log::ALERT);
          return false;
      }
      $durationDivide = Billrun_Util::getIn($matchingPaths, 'duration.divide_to_seconds', 1000);
      if($durationDivide == 0){
        Billrun_Factory::log("Invalid divide_to_seconds value. Can't divide by zero, please change matching_paths.duration.divide_to_seconds to valid value.", Zend_Log::ALERT);
        return false;
      }
      $chargeRate = $matchingChargeConfigurations['chargeRate'] ?? 0; //price in cents per 60 seconds
      $baseCharge = $matchingChargeConfigurations['baseCharge'] ?? 0; //price in cents
      $startInterval = $matchingChargeConfigurations['startInterval'] ?? 0; //in seconds 
      return $baseCharge / 100 + $chargeRate / 100 / 60 * max($duration / $durationDivide - $startInterval, 0);
  }

  protected function getChargeConfigurations($weekChargeConfigurations, $urt){
    if ($this->checkSundayAndHoliday($urt)) {
        $type = "sundayAndHoliday";
        $chargeConfigurations = $weekChargeConfigurations['sundayAndHoliday'];
    } elseif ($this->checkSaturday($urt)) {
        $type = "saturday";
        $chargeConfigurations = $weekChargeConfigurations['saturday'];
    } else {
        $type = "weekday";
        $chargeConfigurations = $weekChargeConfigurations['weekday'];
    }
    if (empty($chargeConfigurations)) {
        Billrun_Factory::log("Empty week charge configurations for urt : " . date("Y-m-d H:i:s", $urt) . ", type: " . $type . ". Tariff switching class revision: " . print_r($tariffSwitchingClassRevision, 1), Zend_Log::ALERT);
        return false;
    }
    return $chargeConfigurations;
  }

  protected function findMatchingOfflineAChargeConfigurations($tariffProfile, $urt) {
    $chargeConfigurations = $this->getChargeConfigurations($tariffProfile['weekChargeConfiguration'], $urt);
    if(!$chargeConfigurations){
        return false;
    }
    return $chargeConfigurations;
}

  protected function findMatchingSwitchingClassesSequence($tariffSwitchingClassRevision, $urt) {
      $chargeConfigurations = $this->getChargeConfigurations($tariffSwitchingClassRevision['weekChargeConfigurations'], $urt);
      if(!$chargeConfigurations){
          return;
      }
      if (count($chargeConfigurations) === 1) {
          return $chargeConfigurations[0]['chargeRateSequenceNumber'];
      }
      array_multisort(array_column($chargeConfigurations, 'sequence'), SORT_ASC, $chargeConfigurations);
      $prevChargeConfiguration = null;
      foreach ($chargeConfigurations as $chargeConfiguration) {
          if (strtotime(date("H:i:s", $urt)) < strtotime($chargeConfiguration['time'])) {
              break;
          }
          $prevChargeConfiguration = $chargeConfiguration;
      }
      if (is_null($prevChargeConfiguration)) {
          $matchChargeConfiguration = end($chargeConfigurations);
          return $matchChargeConfiguration['chargeRateSequenceNumber'];
      }
      return $prevChargeConfiguration['chargeRateSequenceNumber'];
  }

  protected function checkSaturday($date) {
      return date('l', $date) == 'Saturday' ?? false;
  }

  protected function checkSundayAndHoliday($date) {
      if (date('l', $date) == 'Sunday') {
          return true;
      } else {
          $receivedDate = new MongoDate($date);
          $query = array('date' => array('$lte' => $receivedDate), 'dateTo' => array('$gt' => $receivedDate));
          $holidays = $this->nonWorkingDaysCollection->query($query)->cursor()->current();
          if (!$holidays->isEmpty()) {
              return true;
          }
          return false;
      }
  }

  protected function isOnlyOneSequence($tariffProfile) {
      if (count($tariffProfile['chargeConfigurations']) === 1) {
          return $tariffProfile['chargeConfigurations'][0]['sequence'];
      }
      return false;
  }

  protected function convertDestNumberToSubscriberNumber($destNumber, $matchingPaths){
      $convertPatterns = Billrun_Util::getIn($matchingPaths, 'subscriber_number.conversion',Billrun_Util::getIn($matchingPaths, 'subscriber_number.convertion',[]));
      foreach($convertPatterns as $convert){
        $pattern = $convert['pattern'] ?? '';
        $replacement = $convert['replacement'] ?? '';
        $destNumber = preg_replace($pattern, $replacement ,$destNumber);
      }
      return $destNumber;
  }
  protected function pricingCdr($line) {
      $matchingPaths = $this->matchingPathsByType[$line['type']] ?? null;
      $urt =  $line['urt']->sec;
      if (!isset($line['urt'])) {
          Billrun_Factory::log("Failed to get urt from line." . print_r($line, 1), Zend_Log::ALERT);
          return false;
      }
      $inaNumberPath = Billrun_Util::getIn($matchingPaths, 'subscriber_number.path');
      $inaNumber = Billrun_Util::getIn($line, $inaNumberPath);
      if (!$inaNumber) {
          Billrun_Factory::log("Failed to get $inaNumberPath from line." . print_r($line, 1), Zend_Log::ALERT);
          return false;
      }
      $inaNumber = $this->convertDestNumberToSubscriberNumber($inaNumber, $matchingPaths);
      $inaNumberRevison = $this->getMatchingInaNumberRevision($inaNumber, $urt);
      if ($inaNumberRevison === false) {
          Billrun_Factory::log("Failed found matching subscriberNumber  revision for $inaNumberPath in INA numbers collection. subscriberNumber: $inaNumber, urt: ". date("Y-m-d H:i:s", $urt), Zend_Log::DEBUG);
          return false;
      }
      if (!$this->checkIfValidInaNumber($inaNumberRevison, $urt, $inaNumber)) {
          return false;
      }

      $type = $this->getTariffProfileType($inaNumberRevison['tariffProfile']);
      if(!$type){
        return false;
      }
      Billrun_Factory::log("Teldas calculating pricing of ina number $inaNumber with $type tariff profile id: " . $inaNumberRevison['tariffProfile']. " for line " .$line['stamp'], Zend_Log::DEBUG);
      $updateOnlineTariffProfile = Billrun_Util::getIn($this->options, 'update_online');
      if($updateOnlineTariffProfile && $type === "online"){
        return $this->updateOnlineTariffProfile($inaNumberRevison, $urt, $line);
      }
      $updateOfflineATariffProfile = Billrun_Util::getIn($this->options, 'update_offline-a');
      if ($updateOfflineATariffProfile && $type === "offline-a"){
        return $this->updateOfflineATariffProfile($inaNumberRevison, $urt, $line);
      }

  }

  protected function updateOfflineATariffProfile($inaNumberRevison, $urt, $line){
    $tariffProfile = $this->getMatchingTariffProfile($inaNumberRevison['tariffProfile'], $urt);
    if ($tariffProfile === false) {
        return false;
    }
    if (!$this->checkIfValidTariffProfile($tariffProfile, $urt, $inaNumberRevison['tariffProfile'])) {
        return false;
    }
    
    $chargeConfigurations = $this->findMatchingOfflineAChargeConfigurations($tariffProfile, $urt);
    if (!$chargeConfigurations) {
        return false;
    }
    return $this->calcPriceByOfflineAChargeConfigurations($tariffProfile, $chargeConfigurations, $line);
  }

  protected function calcPriceByOfflineAChargeConfigurations($tariffProfile, $chargeConfigurations, $line) {
    $matchingPaths = $this->matchingPathsByType[$line['type']] ?? null;
    $durationPath = Billrun_Util::getIn($matchingPaths, 'duration.path');
    $duration = Billrun_Util::getIn($line, $durationPath) ?? 0;
    if (is_null($duration)) {
        Billrun_Factory::log("Failed to get " . $durationPath . "  from line." . print_r($line, 1), Zend_Log::ALERT);
        return false;
    }
    $durationDivide = Billrun_Util::getIn($matchingPaths, 'duration.divide_to_seconds', 1000);
    $aprice = 0 ;
    $left = (float) $duration / $durationDivide;
    $left = $this->converFieldByRoundingRules($left, 'duration');
    foreach ($chargeConfigurations as $sequence => $chargeConfiguration){
        if($left <= 0){
            break;
        }
        if($sequence + 1 !== $chargeConfiguration['sequence']){
            Billrun_Factory::log("not support unsorted 'chargeConfigurations'. see 'chargeConfigurations' of Tariff Profile id : " . $tariffProfile['id'] , Zend_Log::ALERT);
            return false;
        }
        $ruleType = $chargeConfiguration['ruleType'];
        $chargeRate = $chargeConfiguration['rate'] ?? 0; //price in cents per second
        $interval = $chargeConfiguration['time'] ?? 0; //interval in seconds
        $sign = $chargeConfiguration['sign']; 
        $ruleDuration = $chargeConfiguration['ruleDuration']; 
        if ($ruleDuration == 0){
            $ruleDuration = INF;
        }
        if($sign === 'DEBIT'){
            if($ruleType === 'NOT_PRO_RATA'){
                $useRuleDuration = ceil($left/$interval);

                if($useRuleDuration >= $ruleDuration){
                    $aprice += ($ruleDuration*$chargeRate)/100;
                    $left -= $interval*$ruleDuration;
                }else{
                    $aprice += ($useRuleDuration * $chargeRate)/100;
                    $left -= $interval*ceil($useRuleDuration);
                }
            }elseif($ruleType === 'FIX_PRICE'){
                $aprice += $chargeRate/100;
            }elseif($ruleType === 'PRO_RATA'){
                $useRuleDuration = $left/$interval;
                if($useRuleDuration >= $ruleDuration){
                    $aprice += ($ruleDuration * $chargeRate)/100;
                    $left -= $interval*$ruleDuration;
                }else{
                    $aprice += ($useRuleDuration * $chargeRate)/100;
                    $left -= $interval*ceil($useRuleDuration);
                }
            }else{
                Billrun_Factory::log("Not support ruleType $ruleType of 'chargeConfigurations'. see 'chargeConfigurations' of Tariff Profile id : " . $tariffProfile['id'] , Zend_Log::ALERT);
                return false;
            }
        }else{
            Billrun_Factory::log("Not support sign $sign of 'chargeConfigurations'. see 'chargeConfigurations' of Tariff Profile id : " . $tariffProfile['id'] , Zend_Log::ALERT);
            return false;
        }  
    }
    $aprice = $this->converFieldByRoundingRules($aprice, 'final_charge');
    return $aprice;
  }      

  protected function converFieldByRoundingRules($left, $field){
    $durationRoundingType = Billrun_Util::getIn($this->options, 'rounding_rules.' . $field .'.rounding_type', 'none');
    if($durationRoundingType == 'none'){
        return $left;
    }
    $durationRoundingDecimals = Billrun_Util::getIn($this->options, 'rounding_rules.' . $field . '.rounding_decimals', 2);
    return Billrun_Util::roundingNumber($left, $durationRoundingType, $durationRoundingDecimals);
  }

  protected function getTariffProfileType($id){
    if($id >= 10000 && $id<=19999){
        return "online";
    }elseif ($id >= 20000 && $id <= 29999) {
        return "offline-b";
    }elseif ($id >= 1 && $id <= 9999){
        return "offline-a";
    }else{
        Billrun_Factory::log("Tariff profile type not supported, id: $id ", Zend_Log::ALERT);
        return false;
    }
  }

  protected function updateOnlineTariffProfile($inaNumberRevison, $urt, $line){
    $tariffProfile = $this->getMatchingTariffProfile($inaNumberRevison['tariffProfile'], $urt);
    if ($tariffProfile === false) {
        return false;
    }
    if (!$this->checkIfValidTariffProfile($tariffProfile, $urt, $inaNumberRevison['tariffProfile'])) {
        return false;
    }
    $sequence = $this->isOnlyOneSequence($tariffProfile);
    if ($sequence !== false) {
        return $this->calcPriceByOnlineTariffProfileSequence($tariffProfile, $sequence, $line);
    }

    $tariffSwitchingClassRevision = $this->getMatchingTariffSwitchingClass($tariffProfile['tariffSwitchingClassId'], $urt);
    if ($tariffSwitchingClassRevision === false) {
        return false;
    }
    if (!$this->checkIfValidTariffSwitchingClassId($tariffSwitchingClassRevision, $urt, $tariffProfile['tariffSwitchingClassId'])) {
        return false;
    }

    $sequence = $this->findMatchingSwitchingClassesSequence($tariffSwitchingClassRevision, $urt);
    if (!$sequence) {
        return false;
    }
    return $this->calcPriceByOnlineTariffProfileSequence($tariffProfile, $sequence, $line);
  }

  protected function checkIfValidPrefixInaNumber($inaNumber){
      $inaNumberPrefixes = Billrun_Util::getIn($this->options, 'ina_number_prefixes', "/^(0800|0848|0900|0901|0906|0840|0842|0844|0878)|^18[0-9][0-9]$/");
      return preg_match($inaNumberPrefixes, $inaNumber);
  }
 
  public function afterRealtimeProcessorParsing(&$line, $type){
    return $this->afterGetLineUsageType($line, $type);
  }


  public function afterGetLineUsageType(&$line, $type) {
      $matchingPaths = $this->matchingPathsByType[$line['type']] ?? null;

      if(date_default_timezone_get() != 'Europe/Zurich'){
        Billrun_Factory::log("To use Teldas plugin must have Europe/Zurich timezone.", Zend_Log::ALERT);
        return;
      }
      if (!isset($matchingPaths)) {
          return;
      }
      Billrun_Factory::log("Checking if line "  . $line['stamp'] .  " should be fillter out", Zend_Log::DEBUG);
      if(!$this->lineMatchConditions($line, $matchingPaths)){
        return;
      }
      Billrun_Factory::log("Checking if line "  . $line['stamp'] .  " is Teldas INA number", Zend_Log::DEBUG);
      $urt = $line['urt']->sec;
      if (!isset($line['urt'])) {
          Billrun_Factory::log("Failed to get urt from line." . print_r($line, 1), Zend_Log::ALERT);
          return;
      }
      $inaNumberPath = Billrun_Util::getIn($matchingPaths, 'subscriber_number.path');
      $inaNumber = Billrun_Util::getIn($line, $inaNumberPath);
      if (!$inaNumber) {
          Billrun_Factory::log("Failed to get " . $inaNumberPath . " from line " . $line['stamp'], Zend_Log::DEBUG);
          return;
      }
      $inaNumber = $this->convertDestNumberToSubscriberNumber($inaNumber, $matchingPaths);
      if (!$this->checkIfValidPrefixInaNumber($inaNumber)) {
          return;
      }
      $inaNumberRevison = $this->getMatchingInaNumberRevision($inaNumber, $urt);
      if ($inaNumberRevison === false) {
          return;
      }
      $this->addCfTeldasFieldsByInaNumber($inaNumberRevison, $line);
      $line['usaget'] = Billrun_Util::getIn($matchingPaths, 'usage.type', 'ina_vas_call');
      $line['prepriced'] = true;
    //   $usagevUnit = Billrun_Util::getIn($this->options, 'matching_paths.usage.unit', 'seconds');
    //   $volumeType = Billrun_Util::getIn($this->options, 'matching_paths.volume.type', 'field');
    //   $volumeSrc = Billrun_Util::getIn($this->options, 'matching_paths.volume.src', array('Duration'));
    //   $stampFields = Billrun_Util::getIn($this->options, 'matching_paths.stamps_fields', array());
  }

  protected function lineMatchConditions($line, $matchingPaths){
    if (isset($matchingPaths['conditions']) && !Billrun_Util::areConditionsMet($line, $matchingPaths['conditions'])) {
        Billrun_Factory::log("Line " . $line['stamp'] . " should not be mapped for teldas, conditions are not match." , Zend_Log::DEBUG);
        return false;
    }
    
	return true;	
  }

  public function beforeGetLineAprice($line, &$aprice) {
      $matchingPaths = $this->matchingPathsByType[$line['type']] ?? null;
      if(date_default_timezone_get() != 'Europe/Zurich'){
        Billrun_Factory::log("To use Teldas plugin must have Europe/Zurich timezone.", Zend_Log::ALERT);
        return;
      }
      if (!isset($matchingPaths)) {
          return;
      }
      if ($line['usaget'] != Billrun_Util::getIn($matchingPaths, 'usage.type', 'ina_vas_call')) {
        return;
      }
      $this->priceByStamp[$line['stamp']] = $this->pricingCdr($line);
      $aprice = $this->priceByStamp[$line['stamp']] !== false ? $this->priceByStamp[$line['stamp']] : null;
  }

  public function beforeGetLinePriceToTax($line, &$aprice, $instance) {
      $matchingPaths = $this->matchingPathsByType[$line['type']] ?? null;

      if(date_default_timezone_get() != 'Europe/Zurich'){
        Billrun_Factory::log("To use Teldas plugin must have Europe/Zurich timezone.", Zend_Log::ALERT);
        return;
      }
      if (!isset($matchingPaths)) {
          return;
      }
      if ($line['usaget'] != Billrun_Util::getIn($matchingPaths, 'usage.type', 'ina_vas_call')) {
        return;
      }
      $taxData = $instance->getPreTaxedRowTaxData($line);
      if (isset($taxData['total_amount']) && isset($line['aprice'])) {
          $aprice = $taxData['total_amount'] + $line['aprice'];
      } else {
          if (!isset($this->priceByStamp[$line['stamp']])) {//from queue or calc cpu off                 
              $finalCharge = $this->pricingCdr($line);
              $aprice = $finalCharge !== false ? $finalCharge : null;
          } else {
              $aprice = $this->priceByStamp[$line['stamp']] !== false ? $this->priceByStamp[$line['stamp']] : null;
          }
      }
  }

  protected function addCfTeldasFieldsByInaNumber($inaNumberRevison, &$row){
      if (empty($inaNumberRevison['tariffProfile'])) {
          Billrun_Factory::log("Matching INA number revision not have  tariffProfile. " . print_r($inaNumberRevison, 1), Zend_Log::ALERT);
          return;
      }
      $row['cf']['Tariff'] = "INA_" . strval($inaNumberRevison['tariffProfile']);
  }
  public function getConfigurationDefinitions() {
		return [
      [
        	"type" => "string",
        	"field_name" => "url",
        	"title" => "TelDas URL",
        	"editable" => true,
        	"display" => true,
        	"nullable" => false,
        	"mandatory" => true,
          "default_value" => "https://ws.testsrv.numberportability.ch"
        ],
        [
        	"type" => "string",
        	"field_name" => "user",
        	"title" => "TelDas user",
        	"editable" => true,
        	"display" => true,
        	"nullable" => false,
        	"mandatory" => true
        ],
        [
        	"type" => "password",
        	"field_name" => "password",
        	"title" => "TelDas password",
        	"editable" => true,
        	"display" => true,
        	"nullable" => false,
        	"mandatory" => true
        ],
        [
        	"type" => "number",
        	"field_name" => "initialize_years.limit",
        	"title" => "TelDas initialize years limit",
        	"editable" => true,
        	"display" => true,
        	"nullable" => false,
        	"mandatory" => false,
          "default_value" => 30
        ],
        [
          "type" => "json",
          "field_name" => "matching_paths",
          "title" => "TelDas Matching paths",
          "editable" => true,
          "display" => true,
          "nullable" => false,
          /*
          '[{
            "line_type": "Teles", 
            "duration": {
                "path": "uf.Duration", 
                "divide_to_seconds": 1000
            }, 
            "subscriber_number": {
                "path": "uf.Dest_Number",
                "conversion": [{ 
                    'pattern':'/^\\+41(?=\\d{4}$)/',
                    'replacement':''
                  },
                  {
                    'pattern':'/^\\+41(?=\\d{5}+)/',
                    'replacement':'0'
                  }
                ]
            },
            "usage": {
                "type": "ina_vas_call", 
                "unit": "seconds"
            }
          }]'*/
        ],
        [
          'type' => 'boolean',
          'field_name' => 'update_online',
          'title' => 'TelDas Update online Tariff Profile',
          'mandatory' => false,
          'editable' => true,
          'display' => true,
          'nullable' => false,
          'default_value' => true 
        ],
        [
            'type' => 'boolean',
            'field_name' => 'update_offline-a',
            'title' => 'TelDas Update offline A tariff profiles',
            'mandatory' => false,
            'editable' => true,
            'display' => true,
            'nullable' => false,
            'default_value' => true 
          ],
        [
        	"type" => "string",
        	"field_name" => "ina_number_prefixes",
        	"title" => "TelDas Ina number prefixes",
        	"editable" => true,
        	"display" => true,
        	"nullable" => false,
        	"mandatory" => false,
          'default_value' => "/^(0800|0848|0900|0901|0906|0840|0842|0844|0878)|^18[0-9][0-9]$/"
        ],
        [
          "type" => "json",
          "field_name" => "cron_hours",
          "title" => "TelDas sync hours",
          "editable" => true,
          "display" => true,
          "nullable" => false,
        ],
        [
            "type" => "json",
            "field_name" => "rounding_rules",
            "title" => "TelDas rounding rules",
            "editable" => true,
            "display" => true,
            "nullable" => false,
            /*"{
              duration: {
                 rounding_type : up,
		         rounding_decimals: 2
              }, 
              final_charge: {
                 rounding_type : up,
		         rounding_decimals : 3
              },
            }"*/
          ],
		];
	}


}