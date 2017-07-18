<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing aggregator class for customers leftover records
 *
 * @package  calculator
 * @since    0.5
 */
class Billrun_Aggregator_Leftovers extends Billrun_Aggregator_Customer {

	public function __construct($options = array()) {
		parent::__construct($options);
	}

	/**
	 * load the data to aggregate
	 */
	public function loadData() {
		$billrun_key = $this->getStamp();
		$subscriber = Billrun_Factory::subscriber();
		$filename = $billrun_key . '_leftover_aggregator_input';
		Billrun_Factory::log("Loading file " . $filename, Zend_Log::INFO);
		$billrun_end_time = Billrun_Billingcycle::getEndTime($billrun_key);
		$this->data = $subscriber->getListFromFile('files/' . $filename, $billrun_end_time);
		if (!count($this->data)) {
			Billrun_Factory::log("No accounts were found for leftover aggregator", Zend_Log::ALERT);
		}
		if (is_array($this->data)) {
			$this->data = array_slice($this->data, $this->page * $this->size, $this->size, TRUE);
		}
		Billrun_Factory::log("aggregator entities loaded: " . count($this->data), Zend_Log::INFO);

		Billrun_Factory::dispatcher()->trigger('afterAggregatorLoadData', array('aggregator' => $this));
	}

}
