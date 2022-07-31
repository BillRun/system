<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class holds billrun cycle start and end times.
 * 
 * @package  DataTypes
 * @since    5.2
 */
class Billrun_DataTypes_CycleTime {
	
	/**
	 * Cycle start time
	 * @var int
	 */
	private $start;
	
	/**
	 * Cycle end time
	 * @var int 
	 */
	private $end;
	
	/**
	 * Current billrun key.
	 * @var string
	 */
	private $key;
	
	/**
	 * Number of days in the cycle
	 * @var int
	 */
	private $days;


	/**
	 * Create a new instance of the cycle time class.
	 * @param string $billrunKey - Billrun key to set the cycle times by.
	 */
	public function __construct($billrunKey) {
		$this->key = $billrunKey;
		$this->start = Billrun_Billingcycle::getStartTime($billrunKey);
		$this->end = Billrun_Billingcycle::getEndTime($billrunKey);
	}

	/**
	 * Get the cycle start date
	 * @return int
	 */
	public function start() {
		return $this->start;
	}
	
	/**
	 * Get the cycle end date
	 * @return int
	 */
	public function end() {
		return $this->end;
	}
	
	public function key() {
		return $this->key;
	}
	
	/**
	 * get number of days in the cycle
	 * @return int
	 */
	public function days() {
		if (is_null($this->days)) {
			$this->days = Billrun_Utils_Time::getDaysDiff($this->start(), $this->end());
		}
		return $this->days;
	}
}
