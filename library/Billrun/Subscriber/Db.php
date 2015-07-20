<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing subscriber class based on database
 *
 * @package  Billing
 * @since    4.0
 */
class Billrun_Subscriber_Db extends Billrun_Subscriber {

	/**
	 * method to load subsbscriber details
	 * 
	 * @param array $params load by those params 
	 */
	public function load($params) {
		$queryParams = array();
		if (isset($params['IMSI'])) {
			$queryParams['imsi'] = $params['IMSI'];
		} elseif (isset($params['MSISDN'])) {
			$queryParams['msisdn'] = $params['MSISDN'];
		} else {
			Billrun_Factory::log()->log('Cannot identify subscriber. Require phone or imsi to load. Current parameters: ' . print_R($params, 1), Zend_Log::ALERT);
			return $this;
		}

		if (!isset($params['DATETIME'])) {
			$datetime = date(Billrun_Base::base_dateformat);
		} else {
			$datetime = strtotime($params['DATETIME']);
		}
	
			$queryParams['from'] = array('$lt' => new MongoDate($datetime));
			$queryParams['to'] = array('$gt' => new MongoDate($datetime));


		$data = $this->customerQueryDb($params);

		if (is_array($data)) {
			$this->data = $data;
		} else {
			Billrun_Factory::log()->log('Failed to load subscriber data', Zend_Log::ALERT);
		}
		return $this;
	}
	
	protected function customerQueryDb($params) {
		$coll = Billrun_Factory::db()->subscribersCollection();
		$results = $coll->query($params)->limit(1);
		return $results;
	}

	/**
	 * method to save subsbscriber details
	 */
	public function save() {
		return true;
	}

	/**
	 * method to delete subsbscriber entity
	 */
	public function delete() {
		return true;
	}

	public function isValid() {
		return true;
	}
	
	public function getSubscribersByParams($params, $availableFields) {
		
	}
	
	public function getList($page, $size, $time, $acc_id = null) {
		
	}
	
	public function getListFromFile($file_path, $time) {
		
	}


}
