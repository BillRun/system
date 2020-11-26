<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Represents a commin aggregatble record
 *
 * @package  Cycle
 * @since    5.2
 */
abstract class Billrun_Cycle_Common implements Billrun_Aggregator_Aggregateable {
	
	/**
	 * Array of aggregatable records
	 * @var array
	 */
	protected $records;
	
	protected $cycleAggregator = null;
	
	/**
	 * Create a new instance of the common aggregatable class.
	 * @param array $data - Input data
	 * @param array $baseLine - The base line to concat to all internally aggregateable
	 * lines, empty by default.
	 * @throws Exception
	 */
	public function __construct($data, $cycleAggregator) {
		// Validate
		if(!$this->validate($data)) {
			// TODO: Swap with an actual aggregator exception
			throw new Exception("Internal aggregator error construction data is invalid.");
		}
		$this->cycleAggregator = $cycleAggregator;
		$this->constructRecords($data);
	}

	/**
	 * Construct the internal aggregatable records
	 * @param array $data - Aggregatable input
	 */
	protected abstract function constructRecords($data);
	
	/**
	 * Validate the input
	 * @param array $input - Input to validate.
	 * @return boolean True if valid.
	 */
	protected abstract function validate($input);
	
	/**
	 * Aggregate main action.
	 */
	public function aggregate($data = array()) {
		$results = array();
		foreach ($this->records as $current) {
			$results = array_merge($results , $current->aggregate());
		}
		return $results;
	}
	
	public function getRecords() {
		return $this->records;
	}
}
