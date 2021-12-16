<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class represents a table holding data.
 * Use it to set and get data values.
 * 
 * @package  DataTypes
 * @since    5.2
 */
abstract class Billrun_DataTypes_CachedDataTable {
	
	/**
	 * Dictionary holdind data paired with keys.
	 * @var array 
	 */
	private $dictionary = array();
	
	/**
	 * Get a data value, this function uses the internal set function.
	 * @param mixed $key - Key to get the data by.
	 * @return mixed - Data.
	 */
	public function get($key, $invoicing_day = null) {
		return !is_null($invoicing_day)? $this->_get($key, $invoicing_day) : $this->_get($key);
	}
	
	/**
	 * Set a data value.
	 * @param mixed $key - Key to fetch the data by.
	 * @param mixed $data - Data to store in the table.
	 */
	public function set($key, $data) {
		$this->_set($key, $data);
	}
	
	/**
	 * Function to be executed on setting a new data value, implementation
	 * according to need.
	 * @param mixed $key - Key to fetch the data by.
	 * @param mixed $data - Data to store in the table.
	 */
	protected abstract function onSet($key, $data);
	
	/**
	 * This function is executed when there is no data found for the input key.
	 * If the data is not in the table, this function is to generate the data
	 * value that should be stored.
	 * @param mixed $key - Key to fetch the data by.
	 * @return mixed - Data to store along the key.
	 */
	protected abstract function onGet($key);
	
	/**
	 * Get a data value, this function uses the internal _set function.
	 * @param mixed $key - Key to get the data by.
	 * @return mixed - Data.
	 */
	private function _get($key, $invoicing_day = null) {
		// If the value is in the table, return it.
		$config = Billrun_Factory::config();
		if((!is_null($invoicing_day)) && $config->isMultiDayCycle()){
			$dictionaryKey = $key . $invoicing_day;
		}else {
			$dictionaryKey = $key;
		}
		if(isset($this->dictionary[$dictionaryKey])) {
			return $this->dictionary[$dictionaryKey];
		}
		
		$data = !is_null($invoicing_day) ? $this->onGet($key, $invoicing_day) : $this->onGet($key);
		$this->_set($dictionaryKey, $data);
		return $data;
	}
	
	/**
	 * Set a data value.
	 * @param mixed $key - Key to fetch the data by.
	 * @param mixed $data - Data to store in the table.
	 */
	private function _set($key, $data) {
		$this->onSet($key, $data);
		$this->dictionary[$key] = $data;
	}
	
}
