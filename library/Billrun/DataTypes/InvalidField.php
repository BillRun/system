<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class represents an invalid field.
 *
 * @package  DataTypes
 * @since    5.2
 */
class Billrun_DataTypes_InvalidField {

	/**
	 * The field name
	 * @var string
	 */
	protected $fieldName = null;

	/**
	 * Field error code
	 * @var Error code
	 * @toto validate this value somehow.
	 */
	protected $error = null;

	/**
	 * Create a new instance of the invalid field class.
	 * @param string $fieldName - Name of the field
	 * @param int $errorCode - The error. Empty mendatory by default.
	 */
	public function __construct($fieldName, $errorCode = 1) {
		$this->fieldName = $fieldName;
		$this->error = $errorCode;
	}

	/**
	 * Return the error data
	 * @return array
	 */
	public function output() {
		return array('name' => $this->fieldName, 'error' => $this->error);
	}

	/**
	 * Get the field error code
	 * @return integer
	 */
	public function error() {
		return $this->error;
	}

}
