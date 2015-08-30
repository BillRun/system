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
		$subscriberQuery = $this->getSubscriberQuery($params);
		if($subscriberQuery === false){
			Billrun_Factory::log('Cannot identify subscriber. Require phone or imsi to load. Current parameters: ' . print_R($params, 1), Zend_Log::ALERT);
			return $this;
		}

//		if (!isset($params['time'])) {
//			$datetime = time();
//		} else {
//			$datetime = strtotime($params['time']);
//		}
	
//		$queryParams['from'] = array('$lt' => new MongoDate(strtotime($datetime)));
//		$queryParams['to'] = array('$gt' => new MongoDate($datetime));


		$data = $this->customerQueryDb($subscriberQuery);

		if (is_array($data)) {
			$this->data = $data;
		} else {
			Billrun_Factory::log('Failed to load subscriber data', Zend_Log::ALERT);
		}
		return $this;
	}
	
	/**
	 * Get the query for the collection to get the subscriber by.
	 * @param type $params
	 * @return boolean
	 */
	protected function getSubscriberQuery($params){
		$queryParams = array();
		
		// TODO: Create SubscriberQuery classes for Imsi, msisdn etc.
		if (isset($params['IMSI'])) {
			$queryParams['imsi'] = $params['IMSI'];
		} elseif (isset($params['MSISDN'])) {
			$queryParams['msisdn'] = $params['MSISDN'];
		} elseif(isset($params['sid']) && $params['to'] && $params['from']) {
			$queryParams['sid'] = $params['sid'];
			$queryParams['to']['$lte']   = Billrun_Db::intToMongoDate($queryParams['to']);
			$queryParams['from']['$gte'] = Billrun_Db::intToMongoDate($queryParams['from']);
		} else {
			return false;
		}
		
		return $queryParams;
	}
	
	/**
	 * Get the customer from the db.
	 * @param array $params - Input params to get a subscriber by.
	 * @return array Raw data of mongo raw.
	 */
	protected function customerQueryDb($params) {
		$coll = Billrun_Factory::db()->subscribersCollection();
		$results = $coll->query($params)->cursor()->limit(1)->current();
		if ($results->isEmpty()) {
			return array();
		}
		return $results->getRawData();
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
