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
	protected $start;
	
	/**
	 * Cycle end time
	 * @var int 
	 */
	protected $end;
	
	/**
	 * Current billrun key.
	 * @var string
	 */
	protected $key;
	
	/**
	 * Number of days in the cycle
	 * @var int
	 */
	protected $days;
	
	/**
	 * Cycle's invoicing day - multi day cycle mode
	 * @var string
	 */
	protected $invoicing_day;


	/**
	 * Create a new instance of the cycle time class.
	 * @param string $billrunKey - Billrun key to set the cycle times by.
	 */
	public function __construct($billrunKey, $invoicing_day = null) {
		$this->key = $billrunKey;
		$this->invoicing_day = $invoicing_day;
		$this->start = Billrun_Billingcycle::getStartTime($billrunKey, $invoicing_day);
		$this->end = Billrun_Billingcycle::getEndTime($billrunKey, $invoicing_day);
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
