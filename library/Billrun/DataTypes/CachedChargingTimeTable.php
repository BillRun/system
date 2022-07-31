<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class represents a table holding dates for the charging cycle.
 * Use it to set and get time values.
 * 
 * @package  DataTypes
 * @since    5.2
 */
class Billrun_DataTypes_CachedChargingTimeTable  extends Billrun_DataTypes_CachedDataTable {
	
	/**
	 * String representation of the time base.
	 * This member is used to differentiate charging start and charging end.
	 * The value of the timebase will be applied to the time result of current date.
	 * Example: if the time base is -1 month, then the result of the calculation 
	 * will be todays date minus 1 month.
	 * Null if this logic should not be applied.
	 * Set to empty by default.
	 * @var string
	 */
	protected $timeBase = null;
	
	/**
	 * Create a new instance of a charging time table.
	 * @param string $timeBase - Time base to set to the current table, empty
	 * by default.
	 * @throws InvalidArgumentException
	 */
	public function __construct($timeBase = null) {
		$this->handleTimeBase($timeBase);
	}
	
	/**
	 * Handle the input time base.
	 * @param string $timeBase - Input time base from the constructor
	 * @throws InvalidArgumentException
	 */
	protected function handleTimeBase($timeBase) {
		// If empty, do nothing
		if(!$timeBase) {
			return;
		}
		
		// Validate the input data.
		if(!is_string($timeBase) || (strtotime($timeBase) === false)) {
			throw new InvalidArgumentException(__CLASS__ . ':' . __FUNCTION__ . ':' . __LINE__ . ' ' . ' Received invalid time base value.');
		}
		
		$this->timeBase = $timeBase;
	}
	
	/**
	 * Get a charging time table value
	 * @param string $key - Billrun key to get time by.
	 */
	protected function onGet($key) {
		$datetime = $this->getDatetime($key);
		
		$time = strtotime($datetime);
		
		// If the timebase is not empty, apply it.
		if($this->timeBase &&   strlen($key) !== 14) {
			$time = strtotime($this->timeBase, $time);
		} 
		return $time;
	}

	/**
	 * Return the date constructed from the current billrun key
	 * @param string $billrunKey Billrun key to get the datetime by.
	 * @param int $defaultChargingDay - Default value is 1.
	 * @return string The string representation of current date time according
	 * to the billrun key.
	 */
	protected function getDatetime($billrunKey, $defaultChargingDay=1) {
		$config = Billrun_Factory::config();
		$dayofmonth = $config->getConfigValue('billrun.charging_day', $defaultChargingDay);
		return strlen($billrunKey) == 14 ? $billrunKey : $billrunKey . str_pad($dayofmonth, 2, '0', STR_PAD_LEFT) . "000000";
	}
	
	/**
	 * There is no logic to be executed in this function.
	 * @param type $key
	 * @param type $data
	 */
	protected function onSet($key, $data) {}

}
