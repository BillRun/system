<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Billrun_Account_External extends Billrun_Account {
	use Billrun_Subscriber_External_Cacheable;
	
	protected static $type = 'external';
	
	protected static $queryBaseKeys = ['id', 'time', 'limit'];
	
	protected $remote;
	protected $remote_authentication;
    protected $remote_billable_url;
	protected $remote_billable_authentication;

	const API_DATETIME_REGEX='/^\d{4}-\d{2}-\d{2}[\sT]\d{2}:\d{2}:\d{2}$/';

	public function __construct($options = []) {
		parent::__construct($options);
		$this->remote = Billrun_Factory::config()->getConfigValue(	'subscribers.account.external_url',
																	Billrun_Util::getFieldVal($options['external_url'],	''));
		$defaultAuthentication = Billrun_Factory::config()->getConfigValue('subscribers.external_authentication', []);
		$this->remote_authentication = Billrun_Factory::config()->getConfigValue('subscribers.account.external_authentication', $defaultAuthentication);
		$this->remote_billable_url = Billrun_Factory::config()->getConfigValue('subscribers.billable.url', '');
		$this->remote_billable_authentication = Billrun_Factory::config()->getConfigValue('subscribers.billable.external_authentication', $defaultAuthentication);

		$this->setCacheEnabled(Billrun_Factory::config()->getConfigValue('subscribers.account.external_cache_enabled', false));
		$this->setCachingTTL(Billrun_Factory::config()->getConfigValue('subscribers.account.external_cache_ttl', 300));
	}
	
	public static function getCachingEntityIdKey() {
		return 'aid';
	}

	public function getBillable(\Billrun_DataTypes_MongoCycleTime $cycle, $page = 0 , $size = 100, $aids = [], $invoicing_days = null) {
			$dateFormat = ( (abs($cycle->end()->sec - $cycle->start()->sec) <= 86400 &&
							Billrun_Factory::config()->getConfigValue('subscribers.billable.compatiblity.use_datetime_for_same_day_cycle',true))
								?  // help some CRMs understand if this is an immediate invoice call
								Billrun_Factory::config()->getConfigValue('subscribers.billable.single_day_cycle_format','Y-m-d H:i:s')
								: //  regualr  +1 months  cycle time  format for  start/end fields
								Billrun_Factory::config()->getConfigValue('subscribers.billable.single_day_cycle_format','Y-m-d'));
			// Prepare request
			$requestParams = [
				'start_date' => date($dateFormat,$cycle->start()->sec),
				'end_date' => date($dateFormat ,$cycle->end()->sec),
				'page' => $page,
				'size' => $size
			];

			if(!empty($aids)) {
				$requestParams['aids'] = implode(',',$aids);
			}
			
			if(!empty($invoicing_days)) {
				$requestParams['invoicing_days'] = $invoicing_days;
			}
			Billrun_Factory::log('Sending request to ' . $this->remote_billable_url . ' with params : ' . json_encode($requestParams), Zend_Log::DEBUG);
			//Actually  do the request
			$request = new Billrun_Http_Request($this->remote_billable_url, ['authentication' => $this->remote_billable_authentication]);
			$request->setParameterPost($requestParams);
			$requestTimeout = Billrun_Factory::config()->getConfigValue('subscribers.billable.timeout', Billrun_Factory::config()->getConfigValue('subscribers.timeout', 600));
			$request->setConfig(array('timeout' => $requestTimeout));
			$results = $request->request(Billrun_Http_Request::POST)->getBody();
			
			Billrun_Factory::log('Receive response from ' . $this->remote_billable_url . '. response: ' . $results, Zend_Log::DEBUG);
			
			$results = json_decode($results, true);
			//Check for errors
			if(empty($results)) {
				Billrun_Factory::log('Failed to retrive valid results for billable, remote returned no data.',Zend_Log::WARN);
				return [];
			}
			if( empty($results['status']) || !isset($results['data']) ) {
				Billrun_Factory::log("Remote server return an error (status : {$results['status']}) on request : ".json_encode($requestParams), Zend_Log::ALERT);
				return [];
			}
			if(Billrun_Factory::config()->getConfigValue('subscribers.account.cache_gba_to_gsd.enabled',false) ) {
				$this->saveRevisionsToCache($results['data'],$cycle);
			}
			// Preform translation if needed and return results
			$fieldMapping = ['firstname' => 'first_name', 'lastname' => 'last_name'];
			foreach($results['data'] as &$rev) {
				Billrun_Utils_Mongo::convertQueryMongodloidDates($rev, static::API_DATETIME_REGEX);
				foreach($fieldMapping as $srcField => $dstField) {
					if(isset($rev[$srcField])) {
						$rev[$dstField] = $rev[$srcField];
					}
				}

			}

			return $results;
	}

	protected function saveRevisionsToCache($revs,$cycleData) {
		if(!$this->cacheEnabled) { return false;}
		$idFieldsToples = Billrun_Factory::config()->getConfigValue('subscribers.account.cache_gba_to_gsd.query_fields',['subscriber'=> [['sid','aid'],['sid']], 'account'=>[['aid']]]);
		foreach($revs as $rev) {
			foreach($idFieldsToples[$rev['type']] as $idTople) {
				$queryParams = array_intersect_key($rev, array_flip($idTople));
				if(count($queryParams) !=  count($idTople)) { continue; }
				array_walk($queryParams,function(&$val, $key)  {
					$val = ['key' => $key, 'operator'=> 'equal','value'=>intval($val)];
				},);
				$queryParams = array_values($queryParams);
				if(!empty($queryParams)) {
					$revTo = empty($rev['to']) ? PHP_INT_MAX : strtotime($rev['to']);
					$time = ( $cycleData->end() < $revTo ? $cycleData->end() : $revTo);

					$query = [
								'time' =>  date(Billrun_Base::base_datetimeformat, $time->sec),
								'params' => $queryParams,
								'id' => md5(serialize($queryParams))
							];
					$rev['id'] = $query['id'];

					if($rev['type']=='subscriber') {
						Billrun_Factory::subscriber()->cacheExternalData(['query'=> [$query]],[$rev]);
					} else {
						$this->cacheExternalData(['query'=> [$query]],[$rev]);
					}
				}
			}
		}
		return true;
	}

	/**
	 * @return string
	 */
	protected static function getCachePrefix(): string {
		return 'external_account_';
	}

	/**
	 * Overrides parent abstract method
	 */
	protected function getAccountsDetails($query, $globalLimit = FALSE, $globalDate = FALSE) {
		$requestData = ['query' => $query];
		if($globalLimit) {
			$requestData['limit'] = $globalLimit;
		}
		if($globalDate) {
			$requestData['date'] = $globalDate;
		}

			$res = $this->loadCache($requestData, function($requestData) {
			Billrun_Factory::log('Sending request to ' . $this->remote . ' with params : ' . json_encode($requestData), Zend_Log::DEBUG);
			$params = [
				'authentication' => $this->remote_authentication,
			];
			$request = new Billrun_Http_Request($this->remote, $params);
			$request->setHeaders(['Accept-encoding' => 'deflate', 'Content-Type'=>'application/json']);
			$request->setRawData(json_encode($requestData));
			$requestTimeout = Billrun_Factory::config()->getConfigValue('subscribers.account.timeout', Billrun_Factory::config()->getConfigValue('subscribers.timeout', 600));
			$request->setConfig(array('timeout' => $requestTimeout));
			$res = $request->request(Billrun_Http_Request::POST)->getBody();
			Billrun_Factory::log('Receive response from ' . $this->remote . '. response: ' . $res, Zend_Log::DEBUG);
			return json_decode($res);
		});
		
		$accounts = [];
		if (!$res) {
			Billrun_Factory::log()->log(get_class() . ': could not complete request to ' . $this->remote, Zend_Log::NOTICE);
			return false;
		}
		foreach ($res as $account) {
			Billrun_Utils_Mongo::convertQueryMongodloidDates($account, static::API_DATETIME_REGEX);
			$accounts[] = new Mongodloid_Entity($account);
		}
		return $accounts;
	}
	
	/**
	 * Overrides parent abstract method
	 */
	protected function getAccountDetails($queries, $globalLimit = FALSE, $globalDate = FALSE) {
		$externalQuery = [];
		foreach ($queries as &$query) {
			$query = $this->buildParams($query);
			if (!isset($query['id'])) {
				$query['id'] = Billrun_Util::generateArrayStamp($query);
			}
			$externalQuery['query'][] = $query;
		}
		if($globalLimit) {
			$externalQuery['limit'] = $globalLimit;
		}
		if($globalDate) {
			$externalQuery['date'] = $globalDate;
		}

		$results = $this->loadCache($externalQuery, function($externalQuery) {
		Billrun_Factory::log('Sending request to ' . $this->remote . ' with params : ' . json_encode($externalQuery), Zend_Log::DEBUG);		
		$params = [
			'authentication' => $this->remote_authentication,
		];
		$request = new Billrun_Http_Request($this->remote, $params);
		$request->setHeaders(['Accept-encoding' => 'deflate', 'Content-Type'=>'application/json']);
		$request->setRawData(json_encode($externalQuery));
		$results = $request->request(Billrun_Http_Request::POST)->getBody();
		Billrun_Factory::log('Receive response from ' . $this->remote . '. response: ' . $results ,Zend_Log::DEBUG);

			return json_decode($results, true);
		});

		if (!$results) {
			Billrun_Factory::log()->log(get_class() . ': could not complete request to ' . $this->remote, Zend_Log::NOTICE);
			return false;
		}
		return array_reduce($results, function($acc, $currentAcc) {
			Billrun_Utils_Mongo::convertQueryMongodloidDates($currentAcc, static::API_DATETIME_REGEX);
			$acc[] = new Mongodloid_Entity($currentAcc);
			return $acc;
		}, []);
	}


	
	/** 
	 * Method to Save as 'Close And New' item
	 */
	public function closeAndNew($set_values, $remove_values = array()) {
		return true;
	}
		
	/**
	 * method to make permanent change
	 * on external subscribers mode no need to make updates to account as account is managed by 3rd party
	 * @param type $query
	 * @param type $update
	 * @return true always
	 */
	public function permanentChange($query, $update) {
		return true;
	}



	protected function buildParams(&$query) {

		if (isset($query['EXTRAS'])) {
			unset($query['EXTRAS']);
		}
		$params = [];
		foreach ($query as $key => $value) {
			if (!in_array($key, static::$queryBaseKeys)) {
				if (is_array($value)) {
					foreach ($value as $currKey => $currVal) {
						$params[] = [
						'key' => $key,
						'operator' => preg_replace('/^\$/', '',$currKey), // match the docs
						'value' => $currVal
						];
					}
				} else {
					$params[] = [
						'key' => $key,
						'operator' => 'equal',
						'value' => $value
						];
				}
				unset($query[$key]);
			}
		}
		$query['params'] = $params;
		return $query;
	}

}

