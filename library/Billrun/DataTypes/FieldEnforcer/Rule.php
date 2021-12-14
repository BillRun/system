<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class represents a field enforcer rule which enforces a single rule on input data fields.
 *
 * @package  DataTypes
 * @subpackage FieldEnforcer
 * @since    5.3
 */
class Billrun_DataTypes_FieldEnforcer_Rule {

	const FIELD_NAME_INDEX = 'field_name';

	/**
	 * The field name
	 * @var string
	 */
	protected $fieldName = null;

	/**
	 * Create a new instance of the field enforcer class.
	 * @param array $config - Array of configurations.
	 */
	public function __construct(array $config) {
		$this->fieldName = Billrun_Util::getFieldVal($config[self::FIELD_NAME_INDEX], null);
	}

	/**
	 * Enforce the rule.
	 * Return true if enforce is successful or invalid field if invalid.
	 * @param array $data - The data to enforce the rule on.
	 * @return \Billrun_DataTypes_InvalidField
	 */
	public function enforce(array $data = array()) {
		if (!$this->fieldName) {
			return new Billrun_DataTypes_InvalidField('Empty field name!');
		}
		return true;
	}

}
