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
	protected $vatable = null;
	protected $charges = array();
	protected $stumpLine = array();
	
	/**
	 *
	 * @var Billrun_DataTypes_CycleTime
	 */
	protected $cycle;
	
	public function __construct(array $options) {
		if(!isset($options['plan'], $options['cycle'])) {
			Billrun_Factory::log("Invalid aggregate plan data!");
			return;
		}
	
		$this->plan = $options['plan'];
		$this->cycle = $options['cycle'];
		$this->constructOptions($options);
	}

	/**
	 * Construct data members by the input options.
	 */
	protected function constructOptions(array $options) {
		if(isset($options['line_stump'])) {
			$this->stumpLine = $options['line_stump'];
		}
			
		if(isset($options['charges'])) {
			$this->charges = $options['charges'];
		}
		
		if(isset($options['vatable'])) {
			$this->vatable = $options['vatable'];
		}
	}
	
	// TODO: Implement
	public function getLine() {
		$entries = array();
		foreach ($this->charges as $key => $value) {
			$entry = $this->getFlatLine();
			$entry['aprice'] = $value;
			$entry['charge_op'] = $key;
			$entry['stamp'] = $this->generateLineStamp($entry);
			if(!empty($entry['vatable'])) {
				$taxCalc = Billrun_Calculator::getInstance(array_merge(Billrun_Factory::config()->getConfigValue('tax.calculator'),array('autoload' => false)));
				$entry = $taxCalc->updateRow($entry);
			}
			$entries[] = $entry;
		}
		
		return $entries;
	}
	
	protected function getFlatLine() {
		$flatEntry = array(
			'plan' => $this->plan,
			'name' => $this->plan,
			'process_time' => new MongoDate(),
			'usagev' => 1
		);
		
		if(FALSE !== $this->vatable ) {
			$flatEntry['vatable'] = TRUE;
		}
		
		$merged = array_merge($flatEntry, $this->stumpLine);		
		return $merged;
	}
	
	protected function generateLineStamp($line) {
		return md5($line['charge_op'] .'_'. $line['aid'] . '_' . $line['sid'] . $this->plan . '_' . $this->cycle->start() . $this->cycle->key());
	}
}
