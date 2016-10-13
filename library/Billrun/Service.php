<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing service class
 * Service extend the basic subscriber usage for additional usage counters (beside Plan group)
 *
 * @package  Service
 * @since    5.2
 */
class Billrun_Service {

	use Billrun_Traits_Group;
	
	/**
	 * constructor
	 * set the data instance
	 * 
	 * @param array $params array of parmeters (plan name & time)
	 */
	public function __construct(array $params = array()) {
		if (isset($params['time'])) {
			$time = $params['time'];
		} else {
			$time = time();
		}
		if (isset($params['data'])) {
			$this->data = $params['data'];
		} else if (isset($params['id'])) {
			$this->data = $this->load(new MongoId($params['id']));
		} else if (isset($params['name'])) {
			$this->data = $this->load($params['name'], $time, 'name');
		}
	}
	
	/**
	 * load the service from DB
	 * 
	 * @param mixed $param the value to load by
	 * @param int $time unix timestamp
	 * @param string $loadByField the field to load by the value
	 */
	protected function load($param, $time = null, $loadByField = '_id') {
		if (is_null($time)) {
			$queryTime = new MongoDate();
		} else {
			$queryTime = new MongoDate($time);
		}
		$serviceQuery = array(
			$loadByField => $param,
			'$or' => array(
				array('to' => array('$gt' => $queryTime)),
				array('to' => null)
			)
		);
		$servicesColl = Billrun_Factory::db()->servicesCollection();
		$serviceRecord = $servicesColl->query($serviceQuery)->lessEq('from', $queryTime)->cursor()->current();
		$serviceRecord->collection($servicesColl);
		$this->data = $serviceRecord;

	}

}
