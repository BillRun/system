<?php

class depositPlugin extends Billrun_Plugin_BillrunPluginBase {

		
	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'deposit';
	
	/**
	 * 
	 * @param type $items
	 * @param type $pluginName
	 */
	public function handlerAlert(&$items,$pluginName) {
			if($pluginName != $this->getName() || !$items ) {return;}
		//$this->log->log("Marking down Alert For {$item['imsi']}",Zend_Log::DEBUG);
		$ret = array();
		$db = Billrun_Factory::db();
		$lines = $db->getCollection($db::lines_table);
		foreach($items as $item) {
			$newEvent = new Mongodloid_Entity($item);
			

			unset($newEvent['events_ids']);
			unset($newEvent['events_stamps']);
			$newEvent = $this->addAlertData($newEvent);
			$newEvent['stamp']	= md5(serialize($newEvent));
			$item['event_stamp']= $newEvent['stamp'];
			
			$ret[] = $events->save($newEvent);
		}
		return $ret;
	}

	/**
	 * 
	 * @param type $items
	 * @param type $pluginName
	 * @return array
	 */
	public function handlerMarkDown(&$items, $pluginName) {
		if($pluginName != $this->getName() || !$items ) {return;}
		//$this->log->log("Marking down Alert For {$item['imsi']}",Zend_Log::DEBUG);
		$ret = array();
		$db = Billrun_Factory::db();
		$eventsCol = $db->getCollection(Billrun_Db::events_table);
		foreach($items as &$item) { 
			$eventsCol->update(	array( '_id' => array('$in' => $item['ids']), 
										),
								array('$set' => array(	
											'event_stamp' => $item['_id'],
										)
									),
								array('multiple'=> 1));
		}
		return $ret;
	}
	
	/**
	 * method to collect data which need to be handle by event
	 */
	public function handlerCollect() {
		$db = Billrun_Factory::db();

		$ret = array();
		$ret = array_merge($ret,$this->detectDepositExceeders($db));
		
		$this->log->log(print_R($ret,1),  Zend_Log::DEBUG);
		// unite all the results per imsi
	//	die;
		return $ret;
	}
	
	/**
	 * 
	 * @param Mongodloid_DB $db
	 * @return type
	 */
	protected function detectDepositExceeders(Mongodloid_DB $db) {
		$eventsCol = $db->getCollection(Billrun_Db::events_table);
		$timeWindow= strtotime("-" . Billrun_Factory::config()->getConfigValue('deposit.hourly.timespan','4 hours'));
		$where = array( 
			'$match' => array (
				'event_stamp' => array('$exists'=> false),
//				'deposit_stamp' => array('$exists'=> true),
				'event_type' => array('$ne'=> 'DEPOSITS'),
				'nofity_time' => array('$gte' =>  $timeWindow)
			),
		);
		$group =array(
			'$group' => array(
				"_id" => '$imsi',
				'deposits' => array('$sum' => 1),
				'events_ids' => array('$addToSet'=> '$_id'),
				'imsi' => array('$first'=> '$imsi'),
				'msisdn' => array('$first'=> '$msisdn'),
				'events_stamps' => array('$addToSet' => '$stamp'),
			),
		);
		$project = array(
			'$project' => array(
				"_id" => 0,
				'deposits' => 1,
				'events_ids' => 1,
				'imsi' => 1,
				'msisdn' => 1,
				'events_stamps' => 1,
			),
		);
		$having = array(
			'$match' => array(
				'deposits' => array('$gt' => floatval(Billrun_Factory::config()->getConfigValue('deposit.hourly.thresholds.deposits', 3)))
			),
		);
		
		return $eventsCol->aggregate($where, $group, $project, $having);
	}

	/**
	 * 
	 * @param type $db
	 * @return type
	 *
	protected function detectSMSExceeders($db) {
		$exceeders = array();
		$linesCol = $db->getCollection(Billrun_Db::events_table);
		$where = array(
			'$match' => array(
				'source' => 'nrtrde',
				'record_type' => 'MOC',
				'connectedNumber' => array('$regex' => '^(?!972)'),
				'callEventStartTimeStamp' => array('$gte' => $charge_time),
				'event_stamp' => array('$exists' => false),
				'callEventDuration' => 0,
			),
		);

		$group = array(
			'$group' => array(
				"_id" => '$imsi',
				"sms" => array('$sum' => 1),
				'lines_stamps' => array('$addToSet' => '$stamp'),
			),
		);

		$project = array(
			'$project' => array(
				'imsi' => '$_id',
				'_id' => 0, 
				'sms' => 1,
			),
		);
		
		$having = array(
			'$match' => array(
				'sms' => array('$gte' => Billrun_Factory::config()->getConfigValue('timespan_events.thresholds.smslimit',10)),
			),
		);
		
		$exceeders = $eventsCol->aggregate($where, $group, $having);
		return $exceeders;
	}*/
	
	

	/**
	 * Add data that is needed to use the event object/DB document later
	 * @param Array|Object $event the event to add fields to.
	 * @return Array|Object the event object with added fields
	 */
	protected function addAlertData($newEvent) {
		$type = 'deposit';
		
		$newEvent['value']= $newEvent[$type];
		$newEvent['source']	= $this->getName();
		
		switch($type) {
			case 'deposit':
					$newEvent['threshold']	= Billrun_Factory::config()->getConfigValue('timespan_events.thresholds.deposit', 0);
					$newEvent['units']	= 'DEPOSIT';
					$newEvent['event_type'] = 'DEPOSITS';
				break;
		}
		
		return $newEvent;
	}
	
}
