<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class holds billrun cycle start and end times in mongo values.
 * 
 * @package  DataTypes
 * @since    5.2
 */
class Billrun_DataTypes_MongoCycleTime {

	/**
	 * Cycle start time
	 * @var Mongodloid_Date
	 */
	private $start;

	/**
	 * Cycle end time
	 * @var Mongodloid_Date 
	 */
	private $end;

	/**
	 * Current billrun key.
	 * @var string
	 */
	private $key;

	/**
	 * Create a new instance of the mongo cycle time class, based on cycle time object.
	 * @param Billrun_DataTypes_CycleTime $cycleTime - Cycle time object.
	 */
	public function __construct(Billrun_DataTypes_CycleTime $cycleTime) {
		$this->key = $cycleTime->key();
		$this->start = new Mongodloid_Date($cycleTime->start());
		$this->end = new Mongodloid_Date($cycleTime->end());
	}

	/**
	 * Get the cycle start date
	 * @return Mongodloid_Date
	 */
	public function start() {
		return $this->start;
	}

	/**
	 * Get the cycle end date
	 * @return Mongodloid_Date
	 */
	public function end() {
		return $this->end;
	}

	public function key() {
		return $this->key;
	}

}
