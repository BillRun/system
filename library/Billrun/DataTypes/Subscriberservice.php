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

	/**
	 * Create a new instance of the Subscriberservice class
	 * @param string $name - The name of the service.
	 */
	public function __construct($name) {
		if (!is_string($name)) {
			return;
		}

		$this->name = $name;

		// Get the price from the DB
		$servicesColl = Billrun_Factory::db()->servicesCollection();
		$serviceQuery['name'] = $this->name;
		$service = $servicesColl->query($serviceQuery, array("price" => 1))->cursor()->current();
		if ($service->isEmpty() || !isset($service['price'])) {
			// Signal invalid
			$this->name = null;
			return;
		}

		$this->price = $service['price'];
	}

	public function getName() {
		return $this->name;
	}

	/**
	 * Check if the service is valid.
	 * @return true if valid.
	 */
	public function isValid() {
		if (empty($this->name) || !is_string($this->name) ||
				((!is_numeric($this->price)) && !Billrun_Util::IsIntegerValue($this->price) && !is_array($this->price))) {
			Billrun_Factory::log("Invalid parameters in subscriber service. name: " . print_r($this->name, 1) . " price: " . print_r($this->price, 1));
			return false;
		}

		return $this->checkDB();
	}

	/**
	 * Check if the service exists in the data base.
	 * @param integer $from - From timestamp
	 * @return boolean True if the service exists in the mongo
	 */
	protected function checkDB($from = null) {
		if (!$from) {
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
		$serviceData = array('name' => $this->name, "price" => $this->price);
		return $serviceData;
	}

	/**
	 * Get the price of the service relative to the current billing cycle
	 * @return float - Price of the service relative to the current billing cycle.
	 */
	public function getPrice() {
		return $this->price;
	}

}
