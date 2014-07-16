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
		Billrun_Factory::log()->log("Marking down Alert For $pluginName", Zend_Log::INFO);
		$priority = Billrun_Factory::config()->getConfigValue('alert.priority', array());
		
		$ret = array();
		$events = Billrun_Factory::db()->eventsCollection();
		foreach($items as &$item) {
			$event = new Mongodloid_Entity($item);
			
			unset($event['events_ids']);
			if (isset($event['events_stamps'][0])) {
				$firstStamp = $event['events_stamps'][0];
				$newEvent['firststamp'] = $firstStamp; // to make the event stamp unique
			}
			unset($event['events_stamps']);
			$newEvent = $this->addAlertData($event);
			$newEvent['stamp']	= md5(serialize($newEvent));
			$newEvent['creation_time'] = date(Billrun_Base::base_dateformat);
			foreach($priority as $key => $pri) {
				$newEvent['priority'] = $key;	
				if($event['event_type'] == $pri) {
					break;
				}
			}
			
			$item['event_stamp'] = $newEvent['stamp'];
			
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
		Billrun_Factory::log()->log("Marking down Alert For deposits fraud plugin",Zend_Log::INFO);
		$ret = array();
		$eventsCol = Billrun_Factory::db()->eventsCollection();
		foreach($items as &$item) { 
			$eventsCol->update(	array( '_id' => array('$in' => $item['events_ids']), 
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
	public function handlerCollect($options) {
                if( $options['type'] != 'roaming') { 
                    return FALSE; 
		}
		Billrun_Factory::log()->log("Collect deposits fraud (deposits plugin)", Zend_Log::INFO);
		$eventsCol = Billrun_Factory::db()->eventsCollection();
		$timeWindow= strtotime("-" . Billrun_Factory::config()->getConfigValue('deposit.hourly.timespan','4 hours'));
		$where = array( 
			'$match' => array (
				'event_stamp' => array('$exists'=> false),
//				'deposit_stamp' => array('$exists'=> true),
				'event_type' => array('$ne'=> 'DEPOSITS'),
				'nofity_time' => array('$gte' => new MongoDate ($timeWindow) ),
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
				"_id" => 1,
				'deposits' => 1,
				'events_ids' => 1,
				'imsi' => 1,
				'msisdn' => 1,
				'events_stamps' => 1,
			),
		);
		$having = array(
			'$match' => array(
				'deposits' => array('$gte' => floatval(Billrun_Factory::config()->getConfigValue('deposit.hourly.thresholds.deposits', 3)))
			),
		);
		
		$items = $eventsCol->aggregate($where, $group, $project, $having);

		Billrun_Factory::log()->log("Deposits fraud found " . count($items) . " items", Zend_Log::INFO);

		return $items;
	}
	
	/**
	 * Add data that is needed to use the event object/DB document later
	 * @param Array|Object $event the event to add fields to.
	 * @return Array|Object the event object with added fields
	 */
	protected function addAlertData(&$newEvent) {
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
