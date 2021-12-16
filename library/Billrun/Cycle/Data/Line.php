<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This interface is used to identify lines
 */
abstract class Billrun_Cycle_Data_Line {

	protected $stumpLine = array();
	protected $vatable = null;
	protected $charges = array();
	protected $subscriberFields = array();

	public function __construct($options) {
		$this->constructOptions($options);
	}

	public function getBillableLines() {
		$results = array();
		foreach ($this->charges as $key => $charges) {
			$chargesArr = is_array($charges) && isset($charges[0]) || count($charges) == 0 ? $charges : array($charges);
			foreach ($chargesArr as $charge) {
				$results[] = $this->getLine($key, $charge);
			}
		}
		return $results;
	}

	/**
	 * This function returns an aggregate result
	 */
	abstract protected function getLine($chargeKey, $chargeData);

	/**
	 * 
	 */
	abstract protected function getCharges($options);

	/**
	 * Construct data members by the input options.
	 */
	protected function constructOptions(array $options) {
		if (isset($options['line_stump'])) {
			$this->stumpLine = $options['line_stump'];
		}

		if (isset($options['vatable'])) {
			$this->vatable = $options['vatable'];
		}
		
		if (isset($options['subscriber_fields'])) {
			$this->subscriberFields = $options['subscriber_fields'];
		}

		$this->charges = $this->getCharges($options);
	}

}
