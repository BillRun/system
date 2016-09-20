<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class represents a service to be used by a subscriber.
 */
class Billrun_DataTypes_Subscriberservice {
	protected $name = null;
//	protected $from = null;
//	protected $to = null;
	
	public function __construct(array $options) {
//		if(!isset($options['from'], $options['to'], $options['name'])) {
		if(!isset($options['name'])) {
			return;
		}
		
//		$this->to = $options['to'];
//		$this->from = $options['from'];
		$this->name = $options['name'];
	}
	
	/**
	 * Check if the service is valid.
	 * @return true if valid.
	 */
	public function isValid() {
//		if(empty($this->name) || !is_string($this->name) || empty($this->from) || empty($this->to)) {
		if(empty($this->name) || !is_string($this->name)) {
			return false;
		}
		
		// Validate
//		if($this->validateDates()) {
//			return false;
//		}
		
		return $this->checkDB();
	}
	
	protected function validateDates() {
		// Get the date strings.
		$from = strtotime($this->from);
		$to = strtotime($this->to);
		
		// Validate
		if(!$from || !$to) {
			return false;
		}
		
		// Validate the dates.
		if($from > $to) {
			return false;
		}
		
		// Translate the internal values
		$this->from = $from;
		$this->to = $to;
		
		return true;
	}
	
	/**
	 * Check if the service exists in the data base.
	 * @param integer $from - From timestamp
	 * @return boolean True if the service exists in the mongo
	 */
	protected function checkDB($from=null) {
		if(!$from) {
			$from = time();
		}
		
		// Check in the mongo.
		$servicesColl = Billrun_Factory::db()->servicesCollection();
		$serviceQuery = Billrun_Utils_Mongo::getDateBoundQuery($from, true);
		$serviceQuery['name'] = $this->name;
		$service = $servicesColl->query($serviceQuery)->cursor()->current();
		
		return !$service->isEmpty();
	}
	
	/**
	 * Get the subscriber service in array format
	 * @return array
	 */
	public function getService() {
		return array('name' => $this->name);
//		return array('name' => $this->name, 'from' => $this->from, 'to' => $this->to);
	}
}
