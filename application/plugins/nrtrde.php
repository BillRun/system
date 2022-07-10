<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Fraud NRTRDE plugin
 *
 * @package  Application
 * @subpackage Plugins
 * @since    0.5
 */
class nrtrdePlugin extends Billrun_Plugin_BillrunPluginFraud {

	use Billrun_Traits_FileSequenceChecking,
		Billrun_Traits_FileActions,
	 Billrun_Traits_FraudAggregation;
	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'nrtrde';

	const time_format = 'YmdHis';
	

	
	/**
	 * Rates (the contain prefixes) that should not be alerted upon
	 * @param type $options
	 */
	protected $freeRates = array();

	public function __construct($options = array()) {
		parent::__construct($options);
		$this->outOfSequenceAlertLevel =  Billrun_Factory::config()->getConfigValue('nrtrde.receiver.out_of_seq_log_level',$this->outOfSequenceAlertLevel);
		$this->freeRates = Billrun_Factory::config()->getConfigValue('nrtrde.fraud.free_rates',$this->freeRates);
		$this->initFraudAggregation();
		$this->loadPlmnToPrefixes();
	}

	public function beforeFTPReceive($receiver, $hostname) {
		if ($receiver->getType() != $this->getName()) {
			return true;
		}
		$this->setFilesSequenceCheckForHost($hostname);
		return true;
	}

	/**
	 * method to extend the behaviour after file received with FTP receiver
	 * the method will extract the NRTRDE zip file to the same location
	 * 
	 * @param type $local_path
	 * @param Zend_Ftp_File $file ftp object
	 * @param Billrun_Receiver_Ftp $ftp ftp receiver
	 */
	public function afterFTPFileReceived($local_path, $file, $ftp) {
		//Create filter object
//		$filter = new Zend_Filter_Decompress(
//			array(
//				'adapter' => 'Zend_Filter_Compress_Zip', //Or 'Tar', or 'Gz'
//				'options' => array(
//					'target' => dirname($local_path),
//				)
//			));
//
//		$filter->filter($local_path);
//		
		return true;
	}

	protected function decompress($local_path) {
		//Create filter object
		$filter = new Zend_Filter_Decompress(
			array(
			'adapter' => 'Zend_Filter_Compress_Zip', //Or 'Zend_Filter_Compress_Tar', or 'Zend_Filter_Compress_Gz'
			'options' => array(
				'target' => dirname($local_path),
			)
		));

		$filter->filter($local_path);

		return true;
	}

	public function afterFTPReceived($receiver, $filepaths, $hostname) {
		if ($receiver->getType() != $this->getName()) {
			return true;
		}
		$this->checkFilesSeq($filepaths, $hostname);
		return true;
	}

	/**
	 *  method to extend and add data to the log of the receiver
	 * 
	 * @param array $log_data the data to log
	 * @param Billrun_Receiver $receiver Billrun receiver object
	 * 
	 * @return true success
	 */
	public function beforeLogReceiveFile($log_data, $receiver) {
//		$log_data['source'] = 'nrtrde';
//		
//		return TRUE;
	}

	public function afterDataParsing(&$row, $parser) {
		if ($parser->getType() == 'nrtrde') {
			// make the duration rounded by minute
			if (isset($row['callEventDuration'])) {
				$callEventDuration = $row['callEventDuration'];
				$row['callEventDurationRound'] = ceil($callEventDuration / 60) * 60;
			}

			// add record opening time UTC aligned
			if (isset($row['callEventStartTimeStamp'])) {
				$row['unified_record_time'] = new MongoDate(Billrun_Util::dateTimeConvertShortToIso($row['callEventStartTimeStamp'], $row['utcTimeOffset']));
				$row['urt'] = new MongoDate(Billrun_Util::dateTimeConvertShortToIso($row['callEventStartTimeStamp'], $row['utcTimeOffset']));
			}
		}
	}

	/**
	 * move zip files to backup path after the processing was done
	 * @param Billrun_Processor $processor the proce
	 * @param string $file_path the path of the current processing file.
	 */
	public function afterProcessorRemove($processor, $file_path) {
		if ($processor->getType() != $this->getName()) {
			return;
		}
		$path = Billrun_Factory::config()->getConfigValue($this->getName() . '.processor.zip_move_path', false, 'string');
		if (!$path) {
			return;
		}
		/*if ($processor->retrievedHostname) {
			$path = $path . DIRECTORY_SEPARATOR . $processor->retrievedHostname;
		}

		$path .= DIRECTORY_SEPARATOR . date("Ym");

		if (!file_exists($path)) {
			Billrun_Factory::log()->log("Creating Directory : $path", Zend_Log::DEBUG);
			mkdir($path, 0777, true);
		}*/

		$srcPath = $file_path;
		if (file_exists($srcPath)) {
			Billrun_Factory::log()->log("Saving extracted file to : $path", Zend_Log::DEBUG);
			$movedTo = $this->backup($srcPath, basename($srcPath), $path, false, true );
			if (empty($movedTo)) {
				Billrun_Factory::log()->log(" Failed when trying to save file : " . basename($srcPath) . " to third party path : $path", Zend_Log::ERR);
			}
		}
	}

	/**
	 * method to unzip the processing file of NRTRDE (received as zip archive)
	 * 
	 * @param string $file_path the path of the file
	 * @param Billrun_Processor $processor instance of the processor who dispatch this event
	 * 
	 * @return boolean
	 */
	public function processorBeforeFileLoad(&$file_path, $processor) {
		if ($processor instanceof Billrun_Processor_Nrtrde && file_exists($file_path) && preg_match('/\.(zip|ZIP)$/',$file_path) ) {
			$this->decompress($file_path);
			$file_path = str_replace('.zip', '', $file_path);

			return true;
		}
		return false;
	}

	public function beforeDataParsing($line, $processor) {
		
	}

	/**
	 * method to collect data which need to be handle by event
	 */
	public function handlerCollect($options) {
		if( $options['type'] != 'roaming') { 
			return FALSE;
		}
		$ret = array();
		foreach ($this->fraudConfig['groups'] as $groupName => $groupIds) {
			$oldEvents=array();
			if(!Billrun_Factory::config()->getConfigValue('nrtrde.fraud.ignore_old_events', FALSE)) {
				$oldEvents = $this->collectForGroup($groupName, $groupIds);
			}
			$ret = array_merge($ret, $oldEvents, $this->collectAdvanceEvents($groupName, $groupIds));
		}

		Billrun_Factory::log()->log('NRTRDE plugin located ' . count($ret) . ' items as fraud events for group : '.$groupName, Zend_Log::INFO);

		return $ret;
	}


	protected function postProcessEventResults(&$allEventsResults, &$eventResults, $eventQuery, $ruleName) {
		$this->normalize($allEventsResults, $eventResults, $eventQuery['name']);
		return false;
	}

	protected function prepareRuleQuery($eventQuery, $ruleName) {
		if (isset($eventQuery['locality'])) {
			$eventQuery['query'] = array_merge($eventQuery['query'], $this->getPLMNMatchQuery($eventQuery['locality']));
		}
		return $eventQuery;
	}

	/**
	 * THis  function is used to add local/none-local checks to  aggregation query.
	 * @param type $local shold the check be for local usage or none local usage
	 * @return array  the  query to add to the aggregation query.
	 */
	protected function getPLMNMatchQuery($local = false) {
		$queryLogic = $local ? '$or' : '$and';
		$retQuery = $local ? array($queryLogic => array(array('connectedNumber' => array('$regex' => "^972")), array('$where' => "this.connectedNumber.length <= 8"))) : array($queryLogic => array(array('connectedNumber' => array('$regex' => "/^(?!972)/"))));
		foreach ($this->plmnTransaltion as $plmn => $regxes) {
			foreach ($regxes as $regx) {
				$retQuery[$queryLogic][] = array('sender' => $plmn, 'connectedNumber' => array('$regex' => ($local ? "^$regx" : "^(?!$regx)")));
			}
		}

		return $retQuery;
	}

	/**
	 * load prefixes from the billing
	 */
	protected function loadPlmnToPrefixes() {
		$this->plmnTransaltion = array();

		foreach ($this->fraudConfig['plmn'] as $key => $rateKeys) {
			foreach ($rateKeys as $rateKey) {
				$rate = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue('billing.db'))->ratesCollection()->query(array('key' => $rateKey))->cursor()->limit(1)->current();
				if ($rate && isset($rate['params']['prefix'])) {
					foreach ($rate['params']['prefix'] as $value) {
						$this->plmnTransaltion[$key][] = $value;
					}
				}
			}
		}
	}

	protected function addToProject($valueArr) {
		$retArr = array();
		foreach ($valueArr as $key => $value) {
			$retArr[$key] = array('$cond' => array(true, $value, $value));
		}
		return $retArr;
	}

	protected function collectForGroup($groupName, $groupIds) {
		$lines = Billrun_Factory::db()->linesCollection();
		$charge_time = Billrun_Util::getLastChargeTime(true); // true means return timestamp

		$freePrefixes = (array) call_user_func_array('array_merge',$this->loadRatesPrefixes($this->freeRates));
		// TODO: take it to config ? how to handle variables ?
		$base_match = array(
			'$match' => array(
				'source' => 'nrtrde',
				'unified_record_time' => array('$gte' => new MongoDate($charge_time)), //TODO DEBUG reinstate on push
				'sender' => array('$in' => $groupIds),
				'connectedNumber' => array('$nin' => $freePrefixes),
				
			)
		);
		$where = array(
			'$match' => array(
				'record_type' => 'MOC',
				'connectedNumber' => array('$regex' => '^972'),
				'event_stamp' => array('$exists' => false),
				'deposit_stamp' => array('$exists' => false),
				'callEventDurationRound' => array('$gt' => 0), // not sms
				//'file' => array('$regex' => '^NRBEL'), // limit NRTRDE1  to BICS only
			),
		);

		$group = array(
			'$group' => array(
				"_id" => '$imsi',
				"moc_israel" => array('$sum' => '$callEventDurationRound'),
				'lines_stamps' => array('$addToSet' => '$stamp'),
			),
		);

		$project = array(
			'$project' => array(
				'imsi' => '$_id',
				'_id' => 0,
				'group' => array('$cond' => array(true, $groupName, $groupName,)),
				'moc_israel' => 1,
				'lines_stamps' => 1,
			),
		);

		$having = array(
			'$match' => array(
				'moc_israel' => array('$gte' => Billrun_Factory::config()->getConfigValue('nrtrde.thresholds.moc.israel', 1800, 'int'))
			),
		);

		$ret = array();

		Billrun_Factory::log()->log("nrtrdePlugin::handlerCollect collecting moc_israel exceeders", Zend_Log::DEBUG);
		$moc_israel = $lines->aggregate($base_match, $where, $group, $project, $having);

		$this->normalize($ret, $moc_israel, 'moc_israel');

		$where['$match']['connectedNumber']['$regex'] = '^(?!972)';
		$group['$group']['moc_nonisrael'] = $group['$group']['moc_israel'];
		unset($group['$group']['moc_israel']);
		unset($having['$match']['moc_israel']);
		$having['$match']['moc_nonisrael'] = array('$gte' => Billrun_Factory::config()->getConfigValue('nrtrde.thresholds.moc.nonisrael', 600, 'int'));
		$project['$project']['moc_nonisrael'] = 1;
		unset($project['$project']['moc_israel']);
		Billrun_Factory::log()->log("nrtrdePlugin::handlerCollect collecting moc_nonisrael exceeders", Zend_Log::DEBUG);
		$moc_nonisrael = $lines->aggregate($base_match, $where, $group, $project, $having);
		$this->normalize($ret, $moc_nonisrael, 'moc_nonisrael');

		$where['$match']['record_type'] = 'MTC';
		unset($where['$match']['connectedNumber']);
		$group['$group']['mtc_all'] = $group['$group']['moc_nonisrael'];
		unset($group['$group']['moc_nonisrael']);
		unset($having['$match']['moc_nonisrael']);
		$having['$match']['mtc_all'] = array('$gte' => Billrun_Factory::config()->getConfigValue('nrtrde.thresholds.mtc', 2400, 'int'));
		$project['$project']['mtc_all'] = 1;
		unset($project['$project']['moc_nonisrael']);
		Billrun_Factory::log()->log("nrtrdePlugin::handlerCollect collecting mtc_all exceeders", Zend_Log::DEBUG);
		$mtc = $lines->aggregate($base_match, $where, $group, $project, $having);
		$this->normalize($ret, $mtc, 'mtc_all');

		//sms out to all numbers 
		$where['$match']['record_type'] = 'MOC';
		$where['$match']['callEventDurationRound'] = 0;
		$group['$group']['sms_out'] = $group['$group']['mtc_all'];
		unset($group['$group']['mtc_all']);
		unset($having['$match']['mtc_all']);
		$group['$group']['sms_out'] = array('$sum' => 1);
		$having['$match']['sms_out'] = array('$gte' => Billrun_Factory::config()->getConfigValue('nrtrde.thresholds.smsout', 70, 'int'));
		$project['$project']['sms_out'] = 1;
		unset($project['$project']['mtc_all']);
		Billrun_Factory::log()->log("nrtrdePlugin::handlerCollect collecting sms_out exceeders", Zend_Log::DEBUG);
		$sms_out = $lines->aggregate($base_match, $where, $group, $project, $having);
		$this->normalize($ret, $sms_out, 'sms_out');
		
		//unset($where['$match']['file']); // remove NRTRDE limit		
		unset($where['$match']['event_stamp']);
		//sms out hourly to israel numbers
		unset($group['$group']['sms_out']);
		unset($having['$match']['sms_out']);
		unset($project['$project']['sms_out']);
		$where['$match']['connectedNumber']['$regex'] = '^972';
		$timeWindow = strtotime("-" . Billrun_Factory::config()->getConfigValue('nrtrde.hourly.timespan', '3h'));
		$base_match['$match']['unified_record_time'] = array('$gt' => new MongoDate($timeWindow));
		$group['$group']['sms_hourly'] = array('$sum' => 1);
		$having['$match']['sms_hourly'] = array('$gte' => Billrun_Factory::config()->getConfigValue('nrtrde.hourly.thresholds.smsout_israel', 100, 'int'));
		$project['$project']['sms_hourly'] = 1;
		Billrun_Factory::log()->log("nrtrdePlugin::handlerCollect collecting sms_hourly exceeders", Zend_Log::DEBUG);
		$sms_hourly = $lines->aggregate($base_match, $where, $group, $project, $having);
		$this->normalize($ret, $sms_hourly, 'sms_hourly');
		
		
		//sms out hourly to non israel numbers
		unset($group['$group']['sms_hourly']);
		unset($having['$match']['sms_hourly']);
		unset($project['$project']['sms_hourly']);
		$where['$match']['connectedNumber']['$regex'] = '^(?!972)';
//		$base_match['$match']['unified_record_time'] = array('$gt' => new MongoDate($timeWindow));
		$group['$group']['sms_hourly_nonisrael'] = array('$sum' => 1);
		$having['$match']['sms_hourly_nonisrael'] = array('$gte' => Billrun_Factory::config()->getConfigValue('nrtrde.hourly.thresholds.smsout_nonisrael', 30, 'int'));		
		$project['$project']['sms_hourly_nonisrael'] = 1;
		Billrun_Factory::log()->log("nrtrdePlugin::handlerCollect collecting sms_out non israel exceeders", Zend_Log::DEBUG);
		$sms_hourly_nonisrael = $lines->aggregate($base_match, $where, $group, $project, $having);
		$this->normalize($ret, $sms_hourly_nonisrael, 'sms_hourly_nonisrael');
		
		//hourly call to nonisrael numbers		
		unset($group['$group']['sms_hourly_nonisrael']);
		unset($having['$match']['sms_hourly_nonisrael']);
		unset($project['$project']['sms_hourly_nonisrael']);
//		$base_match['$match']['unified_record_time'] = array('$gt' => new MongoDate($timeWindow));
		$where['$match']['callEventDurationRound'] = array('$gt' => 0);
		$group['$group']['moc_nonisrael_hourly'] = array('$sum' => '$callEventDurationRound');
		$having['$match']['moc_nonisrael_hourly'] = array('$gte' => Billrun_Factory::config()->getConfigValue('nrtrde.hourly.thresholds.mocnonisrael', 3000));
		$project['$project']['moc_nonisrael_hourly'] = 1;
		Billrun_Factory::log()->log("nrtrdePlugin::handlerCollect collecting moc_nonisrael_hourly exceeders", Zend_Log::DEBUG);
		$moc_nonisrael_hourly = $lines->aggregate($base_match, $where, $group, $project, $having);
		$this->normalize($ret, $moc_nonisrael_hourly, 'moc_nonisrael_hourly');

		//hourly call to israel numbers		
		unset($group['$group']['moc_nonisrael_hourly']);
		unset($having['$match']['moc_nonisrael_hourly']);
		unset($project['$project']['moc_nonisrael_hourly']);
		$where['$match']['connectedNumber']['$regex'] = '^972';
//		$base_match['$match']['unified_record_time'] = array('$gt' => new MongoDate($timeWindow));		
		$group['$group']['moc_israel_hourly'] = array('$sum' => '$callEventDurationRound');
		$having['$match']['moc_israel_hourly'] = array('$gte' => Billrun_Factory::config()->getConfigValue('nrtrde.hourly.thresholds.mocisrael', 7200));
		$project['$project']['moc_israel_hourly'] = 1;
		Billrun_Factory::log()->log("nrtrdePlugin::handlerCollect collecting moc_israel_hourly exceeders", Zend_Log::DEBUG);
		$moc_israel_hourly = $lines->aggregate($base_match, $where, $group, $project, $having);
		$this->normalize($ret, $moc_israel_hourly, 'moc_israel_hourly');
		
		Billrun_Factory::log()->log("NRTRDE plugin locate " . count($ret) . " items as fraud events", Zend_Log::INFO);

		return $ret;
	}


	/**
	 * Helper function to integrate advance event collection
	 * @param type $groupName the  group to collect events for
	 * @param type $groupIds the ids array to collect events for.
	 */
	protected function collectAdvanceEvents($groupName, $groupIds) {
		$baseQuery = array(
			'base_match' => array(
				'$match' => array(
					'source' => 'nrtrde',
				),
			),
			'where' => array(
				'$match' => array(
					'deposit_stamp' => array('$exists' => false),
				),
			),
			'group_match' => array(
				'$match' => array(
					'sender' => array('$in' => $groupIds),
				)
			),
			'group' => array(
				'$group' => array(
					"_id" => '$imsi',
					"callEventDurationRound" => array('$sum' => '$callEventDurationRound'),
					"count" => array('$sum' => 1),
					'aprice' => array('$sum' => '$aprice'),
					'lines_stamps' => array('$addToSet' => '$stamp'),
				),
			),
			'translate' => array(
				'$project' => array(
					'imsi' => '$_id',
					'_id' => 0,
					'count' => 1,
					'aprice' =>1,
					'callEventDurationRound' => 1,
					'lines_stamps' => 1,
				),
			),
			'project' => array(
				'$project' => array(
					'imsi' => 1,
					'_id' => 0,
					'group' => array('$cond' => array(true, $groupName, $groupName)),
					'lines_stamps' => 1,
				),
			),
		);

		return $this->collectFraudEvents($groupName, $groupIds, $baseQuery);
	}

	/**
	 * load prefixes from the billing
	 */
	protected function loadRatesPrefixes($keys) {
		$transaltion = array();

		foreach ($keys as $key => $rateKey) {			
				$rate = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue('billing.db'))->ratesCollection()->query(array('key' => $rateKey))->cursor()->limit(1)->current();
				if ($rate && isset($rate['params']['prefix'])) {
					foreach ($rate['params']['prefix'] as $value) {
						$transaltion[$key][] = $value;
					}
			}
		}
		return (array) $transaltion;
	}

	protected function normalize(&$ret, $items, $field) {
		if (!is_array($items) || !count($items)) {
			return false;
		}

		foreach ($items as $item) {
			$imsi = $item['imsi'];
			if (!isset($ret[$imsi])) {
				$ret[$imsi] = is_array($item) ? $item : array();
				$ret[$imsi]['imsi'] = $imsi;
			}

			$ret[$imsi][$field] = $item[$field];

			if (isset($ret[$imsi]['lines_stamps'])) {
				$ret[$imsi]['lines_stamps'] = array_merge($ret[$imsi]['lines_stamps'], $item['lines_stamps']);
			} else {
				$ret[$item['imsi']]['lines_stamps'] = $item['lines_stamps'];
			}
		}

		return true;
	}

	/**
	 * Add data that is needed to use the event object/DB document later
	 * 
	 * @param Array|Object $event the event to add fields to.
	 * @return Array|Object the event object with added fields
	 * 
	 * @todo LOOSE COUPLING !!!
	 */
	protected function addAlertData(&$event) {
		// @todo: WTF?!?! Are you real with this condition???
		
		if (!isset($event['event_type'])) {
			$type = (isset($event['sms_hourly_nonisrael']) ? 'sms_hourly_nonisrael' :
					(isset($event['moc_nonisrael_hourly']) ? 'moc_nonisrael_hourly' :
					(isset($event['moc_israel_hourly']) ? 'moc_israel_hourly' :
					(isset($event['sms_hourly']) ? 'sms_hourly' :
					(isset($event['moc_nonisrael']) ? 'moc_nonisrael' :
					(isset($event['mtc_all']) ? 'mtc_all' :				
					(isset($event['sms_out']) ? 'sms_out' :
												'moc_israel')))))));

			$event['units'] = 'SEC';
			$event['value'] = $event[$type];
			$event['event_type'] = 'NRTRDE_VOICE';

			$event['target_plans'] = $this->fraudConfig['defaults']['target_plans'];
			switch ($type) {
				case 'moc_israel':
					$event['threshold'] = Billrun_Factory::config()->getConfigValue('nrtrde.thresholds.moc.israel', 1800, 'int');
					break;

				case 'moc_nonisrael':
					$event['threshold'] = Billrun_Factory::config()->getConfigValue('nrtrde.thresholds.moc.nonisrael', 600);
					break;

				case 'mtc_all':
					$event['threshold'] = Billrun_Factory::config()->getConfigValue('nrtrde.thresholds.mtc', 7200);
					break;

				case 'sms_out':
					$event['threshold'] = Billrun_Factory::config()->getConfigValue('nrtrde.thresholds.smsout', 70);
					$event['units'] = 'SMS';
					$event['event_type'] = 'NRTRDE_SMS';
					break;

				case 'sms_hourly':
					$event['threshold'] = Billrun_Factory::config()->getConfigValue('nrtrde.hourly.thresholds.smsout_israel', 100);
					$event['units'] = 'SMS';
					$event['event_type'] = 'NRTRDE_HOURLY_SMS';
					break;
				case 'sms_hourly_nonisrael':
					$event['threshold'] = Billrun_Factory::config()->getConfigValue('nrtrde.hourly.thresholds.smsout_nonisrael', 50);
					$event['units'] = 'SMS';
					$event['event_type'] = 'NRTRDE_HOURLY_SMS';
					break;
			
				case 'moc_israel_hourly' :
					$event['threshold'] = Billrun_Factory::config()->getConfigValue('nrtrde.hourly.thresholds.mocisrael', 7200);
					$event['event_type'] = 'NRTRDE_HOURLY_VOICE';

					break;
				case 'moc_nonisrael_hourly':
					$event['threshold'] = Billrun_Factory::config()->getConfigValue('nrtrde.hourly.thresholds.mocnonisrael', 3000);
					$event['event_type'] = 'NRTRDE_HOURLY_VOICE';
					break;
			}
		}
		$event['effects'] = array(
			'key' => 'type',
//			'filter' => array('$in' => array('nrtrde', 'ggsn'))
		);

		return $event;
	}
	
	public function getType() {
		return $this->getName();
	}

}
