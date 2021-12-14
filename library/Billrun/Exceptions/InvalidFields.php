<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class represents an invalid fields exception in the billrun system
 *
 * @package  Exceptions
 * @since    5.2
 */
class Billrun_Exceptions_InvalidFields extends Billrun_Exceptions_Base {

	const ERROR_CODE = 17576;

	/**
	 * List of invalid fields.
	 * @var array
	 */
	protected $invalidFields = array();

	/**
	 * Create a new instance of the invalid fields exception
	 * @param array $invalidFields - Array of invalid fields.
	 */
	public function __construct(array $invalidFields, $message = "Invalid fields.") {
		parent::__construct($message, self::ERROR_CODE);
		$this->setInvalidFields($invalidFields);
	}

	/**
	 * 
	 * @param Billrun_DataTypes_InvalidField $invalidFields array of invalid fields
	 */
	protected function setInvalidFields(array $invalidFields) {
		$this->invalidFields = $this->translateInvalidFieldArray($invalidFields);
	}

	protected function translateInvalidFieldArray($invalidFields) {
		$result = array();
		foreach ($invalidFields as $key => $field) {
			if ($field instanceof Billrun_DataTypes_InvalidField) {
				$translated = $field->output();
			} else {
				$translated = $this->translateInvalidFieldArray($field);
			}
			$result[$key] = $translated;
		}
		return $result;
	}

	/**
	 * Generate the array value to be displayed in the client for the exception.
	 * @return array.
	 */
	protected function generateDisplay() {
		return json_encode($this->invalidFields);
	}

}
