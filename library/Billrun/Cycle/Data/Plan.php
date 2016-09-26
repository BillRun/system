<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class represents the plan data to be aggregated.
 */
class Billrun_Cycle_Data_Plan implements Billrun_Cycle_Data_Line {
	protected $plan = null;
	protected $charges = array();
	protected $stumpLine = array();
	
	public function __construct(array $options) {
		if(!isset($options['plan'])) {
			return;
		}
	
		$this->plan = $options['plan'];
		$this->constructOptions($options);
	}

	/**
	 * Construct data members by the input options.
	 */
	protected function constructOptions(array $options) {
		if(isset($options['stump_line'])) {
			$this->stumpLine = $options['stump_line'];
		}
			
		if(isset($options['charges'])) {
			$this->charges = $options['charges'];
		}
	}
	
	// TODO: Implement
	public function getLine() {
		$entries = array();
		foreach ($this->charges as $key => $value) {
			$entry = $this->getFlatLine();
			$entry['aprice'] = $value;
			$entry['charge_op'] = $key;
			$entries[] = $entry;
		}
		return $entries;
	}
	
	protected function getFlatLine() {
		$flatEntry = array(
			'plan' => $this->plan,
			'process_time' => new MongoDate(),
		);
		$merged = array_merge($flatEntry, $this->stumpLine);
		
		/**
		 * @var Billrun_DataTypes_CycleTime $cycle
		 */
		$cycle = $merged['cycle'];
		unset($merged['cycle']);
		$stamp = md5($merged['aid'] . '_' . $merged['sid'] . $this->plan . '_' . $cycle->start() . $cycle->key());
		$flatEntry['stamp'] = $stamp;
		return $flatEntry;
	}
}
