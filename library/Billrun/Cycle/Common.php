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
	
	/**
	 * Create a new instance of the common aggregatable class.
	 * @param array $data - Input data
	 * @param array $baseLine - The base line to concat to all internally aggregateable
	 * lines, empty by default.
	 * @throws Exception
	 */
	public function __construct($data) {
		// Validate
		if(!$this->validate($data)) {
			// TODO: Swap with an actual aggregator exception
			throw new Exception("Internal aggregator error");
		}
		
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
	public function aggregate() {
		$results = array();
		foreach ($this->records as $current) {
			$results[] = $current->aggregate();
		}
		return $results;
	}
}
