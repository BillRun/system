<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a prototype for a Realtime response action.
 *
 */
class Billrun_ModelValidator_Base {

	protected $options;
	protected $modelName;

	public function __construct($options = array()) {
		$this->options = $options;
		$this->modelName = $this->options['modelName'];
		Billrun_Factory::config()->addConfig(APPLICATION_PATH . '/conf/validation/conf.ini');
	}

	protected function getValidationMethods() {
		return Billrun_Factory::config()->getConfigValue('validation_methods.' . $this->modelName, array());
	}

	public function validate($data) {
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

	protected function validationResponse($result, $errorMsg = '') {
		if (!$result) {
			Billrun_Factory::log('Validation errors: ' . $errorMsg, Zend_Log::INFO);
		}
		return array(
			'validate' => $result,
			'errorMsg' => $errorMsg,
		);
	}

	protected function getMandatoryFields() {
		return Billrun_Factory::config()->getConfigValue($this->modelName . '.fields', array());
	}

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

	protected function getErrorMessage($fieldName, $fieldValue, $fieldType) {
		if (is_array($fieldType)) {
			return '"' . $fieldValue . '" is not a valid value for "' . $fieldType['type'] . '". Available values are: ' . implode(', ', $fieldType['params']);
		}
		return 'field "' . $fieldName . '" must be of type ' . $fieldType;
	}

	protected function validateType($value, $type, $params) {
		$validator = Billrun_TypeValidator_Manager::getValidator($type);
		if (!$validator) {
			return false;
		}
		return $validator->validate($value, $params);
	}

}
