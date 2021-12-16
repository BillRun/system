<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class holds a cycle key, wrapping calls to the static billrun class.
 * 
 * @package  DataTypes
 * @since    5.2
 */
class Billrun_DataTypes_Billrun {
	/**
	 * Billrun key
	 * @var string
	 */
	private $key;
	
	/**
	 * Create a new instance of the billrun object
	 * @param string $billrunKey Billrun key to wrap
	 */
	public function __construct($billrunKey) {
		$this->key = $billrunKey;
	}
	
	public function key() {
		return $this->key;
	}
	
	/**
	 * Checks if a billrun document exists in the db
	 * @param int $aid the account id
	 * @return boolean true if yes, false otherwise
	 */
	public function exists($aid) {
		return Billrun_Billrun::exists($aid, $this->key);
	}
	
	public function existingAccountsQuery() {
		return Billrun_Billrun::existingAccountsQuery($this->key);
	}
}
