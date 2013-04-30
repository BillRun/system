<?php

class ildsPlugin extends Billrun_Plugin_BillrunPluginFraud {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'ilds';

	/**
	 * method to collect data which need to be handle by event
	 */
	public function handlerCollect($options) {
		if($this->getName() != $options['type']) { 
			return FALSE; 
		}
		
		Billrun_Factory::log()->log("ILDS fraud collect handler triggered",  Zend_Log::DEBUG);
		$lines = Billrun_Factory::db()->linesCollection();
		$charge_time = Billrun_Util::getLastChargeTime(false, 20);

		$base_match = array(
			'$match' => array(
				'source' => 'ilds',
			)
		);

		$where = array(
			'$match' => array(
				'event_stamp' => array('$exists' => false),
				'deposit_stamp' => array('$exists' => false),
				'call_start_dt' => array('$gte' => $charge_time),
				'price_customer' => array('$exists' => true),
				'billrun' => array('$exists' => false),
			),
		);

		$group = array(
			'$group' => array(
				"_id" => '$caller_phone_no',
				'msisdn' => array('$first' => '$caller_phone_no'),
				"total" => array('$sum' => '$price_customer'),
				'lines_stamps' => array('$addToSet' => '$stamp'),
			),
		);

		$project = array(
			'$project' => array(
				'caller_phone_no' => '$_id',
				'_id' => 0,
				'msidsn' => 1,
				'total' => 1,
				'lines_stamps' => 1,
			),
		);

		$having = array(
			'$match' => array(
				'total' => array('$gte' => floatval( Billrun_Factory::config()->getConfigValue('ilds.threshold', 100)) )
			),
		);

		$ret = $lines->aggregate($base_match, $where, $group, $project, $having);
		Billrun_Factory::log()->log("ILDS fraud plugin found " . count($ret) . " items",  Zend_Log::DEBUG);

		return $ret;
	}
	
	/**
	 * Add data that is needed to use the event object/DB document later
	 * @param Array|Object $event the event to add fields to.
	 * @return Array|Object the event object with added fields
	 */
	protected function addAlertData(&$newEvent) {
		
		$newEvent['units']	= 'MIN';
		$newEvent['value']	= $newEvent['total'];
		$newEvent['threshold'] = Billrun_Factory::config()->getConfigValue('ilds.threshold', 100);
		$newEvent['event_type']	= 'ILDS';
		$newEvent['msisdn']	= $newEvent['caller_phone_no'];
		return $newEvent;
	}
}