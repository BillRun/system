<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Basic validator for models
 *
 * @since 5.1
 */
class Billrun_ModelValidator_Base {

	protected $options;
	protected $modelName;

	public function __construct($options = array()) {
		$this->options = $options;
		$this->modelName = $this->options['modelName'];
		Billrun_Factory::config()->addConfig(APPLICATION_PATH . '/conf/validation/conf.ini');
	}

	/**
	 * Get validation methods from config by model name.
	 * 
	 * @return array of method names
	 */
	protected function getValidationMethods() {
		return Billrun_Factory::config()->getConfigValue('validation_methods.' . $this->modelName, array());
	}

	/**
	 * Runs validation methods on a model based on it's name.
	 * A valid model is a model that all of it's verification methods passed.
	 * 
	 * @param type $data - the data to validate
	 * @param type $type - the type of action made (new/update/...)
	 * @return array of: 
	 *					"validate" - true/false
	 *					"errorMsg" - error message
	 */
	public function validate($data, $type) {
		$validationMethods = $this->getValidationMethods();
		foreach ($validationMethods as $validationMethod) {
			if (!method_exists($this, $validationMethod)) {
				Billrun_Factory::log('Cannot find validation method "' . $validationMethod . '" for ' . $this->modelName, Zend_Log::DEBUG);
				return $this->validationResponse(false, 'Validation general error');
			}
			if (($res = $this->{$validationMethod}($data)) !== true) {
				return $this->validationResponse(false, $res);
			}
		}

		return $this->validationResponse(true);
	}

	/**
	 * Creates a generic response so all validators return the same structure.
	 * 
	 * @param boolean $result
	 * @param string $errorMsg
	 * @return array of: 
	 *					"validate" - true/false
	 *					"errorMsg" - error message
	 */
	protected function validationResponse($result, $errorMsg = '') {
		if (!$result) {
			Billrun_Factory::log('Validation errors: ' . $errorMsg, Zend_Log::INFO);
		}
		return array(
			'validate' => $result,
			'errorMsg' => $errorMsg,
		);
	}

	/**
	 * Gets the mandatory fields for the model from config according to the model's name
	 * 
	 * @return array of mandatory fields
	 */
	protected function getMandatoryFields() {
		return Billrun_Factory::config()->getConfigValue($this->modelName . '.fields', array());
	}

	/**
	 * Validate mandatory fields for the model. 
	 * If a fields is defined as mandatory makes sure it appears in the $data
	 * 
	 * @param type $data - the data to validate
	 * @return true on success, error message on failure
	 */
	protected function validateMandatoryFields($data) {
		$fields = $this->getMandatoryFields();
		$missingFields = array();

		foreach ($fields as $field) {
			if ($field['mandatory'] &&
				(!array_key_exists($field['field_name'], $data) ||
				(is_array($data[$field['field_name']]) && empty($data[$field['field_name']])) ||
				((is_string($data[$field['field_name']])) && empty(trim($data[$field['field_name']]))))) {
				$missingFields[] = $field['field_name'];
			}
		}

		if (!empty($missingFields)) {
			return "The following fields are missing: " . implode(', ', $missingFields);
		}
		return true;
	}

	/**
	 * Validate field types.
	 * 
	 * @param type $data - the data to validate
	 * @return true on success, error message on failure 
	 */
	protected function validateTypeOfFields($data) {
		$fields = Billrun_Factory::config()->getConfigValue($this->collection_name . '.fields', array());
		$typeFields = array();
		foreach ($fields as $field) {
			if (isset($field['type'])) {
				$typeFields[$field['field_name']] = $field['type'];
			}
		}
		return $this->validateTypes($data, $typeFields);
	}

	/**
	 * Validate fields' types
	 * 
	 * @param type $data - the data to validate
	 * @param type $typeFields - the field objects to validate by
	 * @return true on success, error message omn failure
	 */
	protected function validateTypes($data, $typeFields) {
		$wrongTypes = array();

		foreach ($typeFields as $fieldName => $fieldType) {
			$type = (!is_array($fieldType) ? $fieldType : $fieldType['type']);
			$params = (!is_array($fieldType) ? array() : $fieldType['params']);
			if (isset($data[$fieldName]) && !$this->validateType($data[$fieldName], $type, $params)) {
				$wrongTypes[$fieldName] = $fieldType;
			}
		}

		if (!empty($wrongTypes)) {
			$ret = array();
			foreach ($wrongTypes as $fieldName => $fieldType) {
				$ret[] = $this->getErrorMessage($fieldName, $data[$fieldName], $fieldType);
			}
			return implode(', ', $ret);
		}
		return true;
	}

	/**
	 * Build a pretty error message for a field that does not mathces it's defined type
	 * 
	 * @param type $fieldName
	 * @param type $fieldValue
	 * @param type $fieldType
	 * @return string error message
	 */
	protected function getErrorMessage($fieldName, $fieldValue, $fieldType) {
		if (is_array($fieldType)) {
			return '"' . $fieldValue . '" is not a valid value for "' . $fieldType['type'] . '". Available values are: ' . implode(', ', $fieldType['params']);
		}
		return 'field "' . $fieldName . '" must be of type ' . $fieldType;
	}

	/**
	 * Validates a type using TypeValidators
	 * 
	 * @param type $value
	 * @param type $type
	 * @param type $params - extra additional params
	 * @return boolean
	 */
	protected function validateType($value, $type, $params) {
		$validator = Billrun_TypeValidator_Manager::getValidator($type);
		if (!$validator) {
			return false;
		}
		return $validator->validate($value, $params);
	}

}
