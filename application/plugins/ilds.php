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
				'deposit_stamp' => array('$exists' => false),
				'call_start_dt' => array('$gte' => $charge_time),
				'price_customer' => array('$exists' => true),
			),
		);

		$group = array(
			'$group' => array(
				"_id" => '$imsi',
				"total" => array('$sum' => '$price_customer'),
				'lines_stamps' => array('$addToSet' => '$stamp'),
			),
		);

		$project = array(
			'$project' => array(
				'caller_phone_no' => '$_id',
				'_id' => 0,
				'total' => 1,
			),
		);

		$having = array(
			'$match' => array(
				'total' => array('$gte' => $this->getConfigValue('ilds.threshold', 100))
			),
		);

		$ret = $lines->aggregate($where, $group, $project, $having);
		
		return $ret;

	}


}