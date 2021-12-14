<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This Trait is used to add start and end logic to the object.
 *
 */
trait Billrun_Traits_DateSpan {

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
	 * Check if the service is valid.
	 * @return true if valid.
	 */
	public function isValid() {
		if (!$this->activation && $this->deactivation) {
			Billrun_Factory::log("Deactivation cannot exist without activation");
			return false;
		}

		if ($this->deactivation && $this->activation && ($this->deactivation < $this->activation)) {
			Billrun_Factory::log("Invalid date values, activation: " . print_r($this->activation) . " deactivation: " . $this->deactivation);
			return false;
		}

		return true;
	}

	/**
	 * Set the span dates with data
	 * @param array $data
	 */
	protected function setSpan($data) {
		if (isset($data['start'])) {
			$this->activation = $data['start'];
		}

		if (isset($data['end'])) {
			$this->deactivation = $data['end'];
		}
	}

}
