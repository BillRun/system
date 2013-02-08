<?php

class ggsnPlugin extends Billrun_Plugin_BillrunPluginFraud {

	
	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'ggsn';

	/**
	 * method to collect data which need to be handle by event
	 */
	public function handlerCollect() {
		$db = Billrun_Factory::db();
		$lines = $db->getCollection($db::lines_table);
		$charge_time = $this->get_last_charge_time();

		$aggregateQuery = $this->getBaseAggregateQuery($charge_time); 
		
		$dataExceedersAlerts = $this->detectDataExceeders($lines, $aggregateQuery);
		$hourlyDataExceedersAlerts = $this->detectHourlyDataExceeders($lines, $aggregateQuery);
		
		return array_merge($dataExceedersAlerts, $hourlyDataExceedersAlerts);
	}
	
	public function afterFTPFileReceived(&$receivedPath, $filename, $receiver) {
		if($receiver->getType() != 'ggsn') { return; } 
		
		$this->verifyFileSequence($filename);
	}
	
	public function handlingLocalFilesReceive($receiver, &$srcPath, $filename) {
		if($receiver->getType() != 'ggsn') { return; } 

		$this->verifyFileSequence($filename);
	}
	
	/**
	 * Check that the received files are in the proper order.
	 * @param $filename the recieve filename.
	 */
	protected $lastSequenceNumber = false;
	protected function verifyFileSequence($filename) {
		$pregResults = array();
		if(!preg_match("/\w+_-_(\d+).\d+_-_\d+\+\d+/",$filename, $pregResults) ) {
				Billrun_Factory::log()->log("GGSN Reciever : Couldnt parse received file : $filename !!!!, last sequence was {$this->lastSequenceNumber}",  Zend_Log::ALERT);			
		}
		
		$sequenceNumber = intval($pregResults[1],10);
		if($this->lastSequenceNumber) {
			if( $this->lastSequenceNumber + 1 != $sequenceNumber  ) {
				$msg = "GGSN Reciever : Received a file out of sequence - for file $filename , last sequence was : {$this->lastSequenceNumber}, current sequence is : {$sequenceNumber} ";
				//TODO use a common mail agent.
				if(!mail(Billrun_Factory::config()->getConfigValue('receiver.errors.email.notify'), 'GGSN file out of sequence', $msg)) {
					Billrun_Factory::log()->log("COULDNT SEND EMAIL!!!!!",  Zend_Log::CRIT);
				} 
				Billrun_Factory::log()->log($msg,  Zend_Log::ALERT);
			}
		}
		$this->lastSequenceNumber = $sequenceNumber;
	}


	/**
	 * Detect data usage above an houlrly limit
	 * @param Mongoldoid_Collection $linesCol the db lines collection
	 * @param Array $aggregateQuery the standard query to aggregate data (see $this->getBaseAggregateQuery())
	 * @return Array containing all the hourly data excceders.
	 */
	protected function detectHourlyDataExceeders($linesCol, $aggregateQuery) {
		$exceeders = array();
		$timeWindow= strtotime("-" . Billrun_Factory::config()->getConfigValue('ggsn.hourly.timespan','4 hours'));
		$limit = floatval(Billrun_Factory::config()->getConfigValue('ggsn.hourly.thresholds.datalimit',0));
		$aggregateQuery[0]['$match']['$and'] =  array( array('record_opening_time' => array('$gte' => date('YmdHis',$timeWindow))),
														array('record_opening_time' => $aggregateQuery[0]['$match']['record_opening_time']) );						
	
		unset($aggregateQuery[0]['$match']['sgsn_address']);
		unset($aggregateQuery[0]['$match']['record_opening_time']);
		
		$having =	array(
				'$match' => array(
					'$or' => array(
							array( 'download' => array( '$gte' => $limit ) ),
							array( 'upload' => array( '$gte' => $limit ) ),		
					),
				),
			);
		foreach($linesCol->aggregate(array_merge($aggregateQuery, array($having))) as $alert) {
			$alert['units'] = 'KB';
			$alert['value'] = ($alert['download'] > $limit ? $alert['download'] : $alert['upload']);
			$alert['threshold'] = $limit;
			$alert['event_type'] = 'GGSN_HOURLY_DATA';
			$exceeders[] = $alert;
		}
		return $exceeders;
	}

	protected function detectDataExceeders($lines,$aggregateQuery) {
		$limit = floatval(Billrun_Factory::config()->getConfigValue('ggsn.thresholds.datalimit',1000));
		$dataThrs =	array(
				'$match' => array(
					'$or' => array(
							array( 'download' => array( '$gte' => $limit ) ),
							array( 'upload' => array( '$gte' => $limit ) ),		
					),
				),
			);
		$dataAlerts = $lines->aggregate(array_merge($aggregateQuery, array($dataThrs)) );
		foreach($dataAlerts as &$alert) {
			$alert['units'] = 'KB';
			$alert['value'] = ($alert['download'] > $limit ? $alert['download'] : $alert['upload']);
			$alert['threshold'] = $limit;
			$alert['event_type'] = 'GGSN_DATA';
		}
		return $dataAlerts;
	}
	
	protected function detectDurationExceeders($lines,$aggregateQuery) {
		$threshold = floatval(Billrun_Factory::config()->getConfigValue('ggsn.thresholds.duration',2400));
		unset($aggregateQuery[0]['$match']['$or']);
		
		$durationThrs =	array(
				'$match' => array(
					'duration' => array('$gte' => $threshold )
				),
			);
		
		$durationAlert = $lines->aggregate(array_merge($aggregateQuery, array($durationThrs)) );
		foreach($durationAlert as &$alert) {
			$alert['units'] = 'SEC';
			$alert['value'] = $alert['duration'];
			$alert['threshold'] = $threshold;
			$alert['event_type'] = 'GGSN_DATA_DURATION';
		}
		return $durationAlert;
	}
	
	/**
	 * Get the base aggregation query.
	 * @param type $charge_time the charge time of the billrun (records will not be pull before that)
	 * @return Array containing a standard PHP mongo aggregate query to retrive  ggsn entries by imsi.
	 */
	protected function getBaseAggregateQuery($charge_time) {
		return array(
				array(
					'$match' => array(
						'type' => 'ggsn',
						'deposit_stamp' => array('$exists' => false),
						'event_stamp' => array('$exists' => false),
						'record_opening_time' => array('$ne' => $charge_time),
						'sgsn_address' => array('$regex' => '^(?!62\.90\.|37\.26\.)'),
						'$or' => array(
										array('download' => array('$gt' => 0 )),
										array('upload' => array('$gt' => 0))
									),
					),
				),
				array(
					'$group' => array(
						"_id" => array('imsi'=>'$served_imsi','msisdn' =>'$served_msisdn'),
						"download" => array('$sum' => '$fbc_downlink_volume'),
						"upload" => array('$sum' => '$fbc_uplink_volume'),
						"duration" => array('$sum' => '$duration'),
						'lines_stamps' => array('$addToSet' => '$stamp'),
					),	
				),
				array(
					'$project' => array(
						'_id' => 0,
						'download' => array('$multiply' => array('$download',0.001)),
						'upload' => array('$multiply' => array('$download',0.001)),
						'duration' => 1,
						'imsi' => '$_id.imsi',
						'msisdn' => array('$substr'=> array('$_id.msisdn',5,10)),
						'lines_stamps' => 1,
					),
				),
			);
	}

	protected function addAlertData(&$event) {
		return $event;
	}
}