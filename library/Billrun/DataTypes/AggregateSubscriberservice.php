<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class represents a service to be used by the subscriber aggregation logic.
 */
class Billrun_DataTypes_AggregateSubscriberservice {

	/**
	 * The service
	 * @var Billrun_DataTypes_Subscriberservice
	 */
	protected $service = null;

	/**
	 * Epoch value representing the activation of the service.
	 * @var int
	 */
	protected $activation = null;

	/**
	 * Epoch value representing the deactivation of the service.
	 * @var int
	 */
	protected $deactivation = null;

	/**
	 * Create a new instance of the Subscriberservice class
	 * @param array $options - Array of options containing name.
	 */
	public function __construct(array $options) {
		if (!isset($options['name'])) {
			return;
		}

		$this->service = new Billrun_DataTypes_Subscriberservice($options['name']);

		if (isset($options['activation'])) {
			$this->activation = $options['activation'];
		}
		if (isset($options['deactivation'])) {
			$this->deactivation = $options['deactivation'];
		}
	}

	public function getName() {
		return $this->service->getName();
	}

	/**
	 * Check if the service is valid.
	 * @return true if valid.
	 */
	public function isValid() {
		if (!$this->service || !$this->service->isValid()) {
			return false;
		}

		if (!empty($this->activation)) {
			Billrun_Factory::log("Invalid service activation value");
			return false;
		}

		if ($this->deactivation && ($this->deactivation < $this->activation)) {
			Billrun_Factory::log("Invalid service date values, activation: " . print_r($this->activation) . " deactivation: " . $this->deactivation);
			return false;
		}

		return true;
	}

	/**
	 * Get the subscriber service in array format
	 * @return array
	 */
	public function getService() {
		$serviceRaw = $this->service->getService();
		$serviceRaw['activation'] = $this->activation;
		if ($this->deactivation) {
			$serviceRaw['deactivation'] = $this->deactivation;
		}
		return $serviceRaw;
	}

}
