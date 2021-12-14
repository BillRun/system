<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class represents a field enforcer rule which enforces the input type.
 *
 * @package  DataTypes
 * @subpackage FieldEnforcer
 * @since    5.3
 */
class Billrun_DataTypes_FieldEnforcer_Type extends Billrun_DataTypes_FieldEnforcer_Rule {

	const TYPE_INDEX = 'type';

	/**
	 * Field type validator
	 * @var Billrun_TypeValidator_Base
	 */
	protected $type = null;

	/**
	 * Create a new instance of the field enforcer class.
	 * @param array $config - Array of configurations.
	 */
	public function __construct(array $config) {
		parent::__construct($config);
		$typeName = Billrun_Util::getFieldVal($config[self::TYPE_INDEX], null);
		if ($typeName) {
			// Try to create a type validator.
			$this->type = Billrun_TypeValidator_Manager::getValidator($typeName);
		}
	}

	/**
	 * Enforce the rule.
	 * Return true if enforce is successful or invalid field if invalid.
	 * @param array $data - The data to enforce the rule on.
	 * @return \Billrun_DataTypes_InvalidField
	 */
	public function enforce(array $data) {
		$parentResult = parent::enforce($data);

		if ($parentResult !== true) {
			return $parentResult;
		}

		if (!$this->type) {
			// TODO: Decide which error code will be "invalid type".
			return new Billrun_DataTypes_InvalidField($this->fieldName);
		}

		// Check if exists.
		if (!isset($data[$this->fieldName])) {
			// This is not an error!
			return true;
		}

		if (!$this->type->validate($data[$this->fieldName])) {
			return new Billrun_DataTypes_InvalidField($this->fieldName, 2);
		}

		return true;
	}

}
