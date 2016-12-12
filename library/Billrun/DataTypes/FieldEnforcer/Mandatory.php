<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
	
/**
 * This class represents a field enforcer rule which enforces the mandtory condition.
 *
 * @package  DataTypes
 * @subpackage FieldEnforcer
 * @since    5.3
 */
class Billrun_DataTypes_FieldEnforcer_Mandatory extends Billrun_DataTypes_FieldEnforcer_Rule {
	
	/**
	 * Enforce the rule.
	 * Return true if enforce is successful or invalid field if invalid.
	 * @param array $data - The data to enforce the rule on.
	 * @return \Billrun_DataTypes_InvalidField
	 */
	public function enforce(array $data = array()) {
		$parentResult = parent::enforce($data);
			
		if($parentResult !== true) {
			return $parentResult;
		}
		
		// Check if exists.
		if(!isset($data[$this->fieldName])) {
			// This IS an error!
			return new Billrun_DataTypes_InvalidField($this->fieldName,1);
		}
		
		return true;
	}
}
