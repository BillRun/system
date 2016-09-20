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
	
	/**
	 * The name of the service
	 * @var string 
	 */
	protected $name = null;
	
	/**
	 * The price of the service
	 * @var float
	 */
	protected $price = null;
	
	public function __construct(array $options) {
		if(!isset($options['name'], $options['price'])) {
			return;
		}
		
		$this->name = $options['name'];
		$this->price = $options['price'];
	}
	
	public function getName() {
		return $this->name;
	}
	
	/**
	 * Check if the service is valid.
	 * @return true if valid.
	 */
	public function isValid() {
		if(empty($this->name) || !is_string($this->name) || 
		  (!is_float($this->price)) && !Billrun_Util::IsIntegerValue($this->price)) {
			return false;
		}
		
		return $this->checkDB();
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
		$serviceQuery['name'] = $this->name;
		$service = $servicesColl->query($serviceQuery)->cursor()->current();
		
		return !$service->isEmpty();
	}
	
	/**
	 * Get the subscriber service in array format
	 * @return array
	 */
	public function getService() {
		return array('name' => $this->name, "price" => $this->price);
	}
	
	/**
	 * 
	 * @param type $billrunKey
	 * @return int
	 * @todo This should be moved to a more fitting location
	 */
	protected function calcFractionOfMonth($billrunKey) {
		$start = Billrun_Billrun::getStartTime($billrunKey);
		$end = Billrun_Billrun::getEndTime($billrunKey);
		$days_in_month = (int) date('t', $start);
		if ($end < $start) {
			return 0;
		}
		$start_day = date('j', $start);
		$end_day = date('j', $end);
		$start_month = date('F', $start);
		$end_month = date('F', $end);

		if ($start_month == $end_month) {
			$days_in_plan = (int) $end_day - (int) $start_day + 1;
		} else {
			$days_in_previous_month = $days_in_month - (int) $start_day + 1;
			$days_in_current_month = (int) $end_day;
			$days_in_plan = $days_in_previous_month + $days_in_current_month;
		}

		$fraction = $days_in_plan / $days_in_month;
		return $fraction;
	}
	
	/**
	 * Get the price of the service relative to the current billing cycle
	 * @param string $billrunKey - Current billrun key
	 * @return float - Price of the service relative to the current billing cycle.
	 */
	public function getPrice($billrunKey) {
		return $this->price * $this->calcFractionOfMonth($billrunKey);
	}
}
