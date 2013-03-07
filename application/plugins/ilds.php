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
	public function handlerCollect() {
		$db = Billrun_Factory::db();
		$lines = $db->getCollection($db::lines_table);
		$charge_time = $this->get_last_charge_time();

		$where = array(
			'$match' => array(
				'source' => 'ilds',
				'event_stamp' => array('$exists' => false),
				'deposit_stamp' => array('$exists' => false),
				'call_start_dt' => array('$gte' => $charge_time),
				'price_customer' => array('$exists' => true),
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

		$ret = $lines->aggregate($where, $group, $project, $having);

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
		$newEvent['msisdn']	= $event['caller_phone_no'];
		return $newEvent;
	}
}