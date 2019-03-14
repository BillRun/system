<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing aggregator class for customers records which received customer data and does not need to fetch it from the DB
 *
 * @package  calculator
 * @since version 5
 */
class Billrun_Aggregator_Customernondb extends Billrun_Aggregator_Customer {
	
	protected $data = [];
	protected $aid = null;
	
	public function __construct($options = array()) {
		parent::__construct($options);
		$this->data = $options['data'] ?: [];
		$this->aid = $options['aid'] ?: null;
	}
	
	/**
	 * see parent::loadRawData
	 */
	protected function loadRawData2($cycle) {
		Billrun_Factory::dispatcher()->trigger('beforeTranslateCustomerAggregatorData', array($this));
		$translatedData = $this->translateCustomerData($this->data);
		Billrun_Factory::dispatcher()->trigger('afterTranslateCustomerAggregatorData', array($this, $translatedData));
		return [
			'data' => $translatedData,
		];
	}
	
	/**
	 * Translates the data received to customer aggregator requirements
	 * 
	 * @param array $data
	 * @return array
	 */
	protected function translateCustomerData($data) {
		return $data;
	}
	
	public function getData() {
		return $this->data;
	}
	
	public function getAid() {
		return $this->aid;
	}
}
