<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This Trait is used to validate entity fields.
 *
 */
trait Billrun_Traits_FieldValidator {

	/**
	 * Return the collection instance.
	 * This is used to validate the uniqeness of sensitive input values.
	 * @return Mongodloid_Collection 
	 * @note '_getCollection' is an abstract function of the trait Billrun_Traits_FieldValidator.
	 * It's named with an underscore to avoid a clash between another getCollection function.
	 */
	abstract protected function _getCollection();

	/**
	 * Return the base query of the action.
	 * This is used to validate the uniqeness of sensitive input values.
	 * @return array
	 * @note '_getBaseQuery' is a function of the Billrun_Traits_FieldValidator trait, 
	 * its default implementation is empty.
	 * It's named with an underscore to avoid a clash between another getBaseQuery function.
	 */
	protected function _getBaseQuery() {
		return array();
	}

	/**
	 * Get the list of field enforcers.
	 * @param array $fieldConfiguration - The array of config data 
	 * defining the fields constraints.
	 */
	protected function getFieldValidators(array $fieldConfiguration) {
		$validators = array();
		foreach ($fieldConfiguration as $currentConfiguration) {
			$fieldName = Billrun_Util::getFieldVal($currentConfiguration['field_name'], null);
			if (!$fieldName) {
				Billrun_Factory::log("Corrupted config data: " . print_r($currentConfiguration, 1), Zend_Log::NOTICE);
				continue;
			}

			// Add the collection
			$currentConfiguration['collection'] = $this->_getCollection();
			$currentConfiguration['base_query'] = $this->_getBaseQuery();

			$validators[$fieldName] = $this->getValidatorsForField($currentConfiguration);
		}
		return $validators;
	}

	/**
	 * Get the list of enforcer names to be bypassed.
	 * Override this function if you need to bypass a constraint, like
	 * mandatory or unique.
	 * @return array
	 */
	protected function getBypassList() {
		return array();
	}

	/**
	 * Get all the validators for a field.
	 * @param array $fieldConf - Field configuration (from the config collection).
	 * @return array of rule enforcers
	 */
	protected function getValidatorsForField(array $fieldConf) {
		$validators = array();
		$bypass = $this->getBypassList();
		foreach ($fieldConf as $key => $value) {
			$lowerKey = strtolower($key);
			// If the key is bypassed, skip the enforcer.
			if (in_array($lowerKey, $bypass)) {
				continue;
			}

			// If the value is false skip the enforcer.
			if (!$value) {
				continue;
			}
			// Construct the validator's name
			// TODO: Move this magic to a const.
			$validatorName = "Billrun_DataTypes_FieldEnforcer_" . ucfirst($lowerKey);

			// Check if the validator exists.
			if (!@class_exists($validatorName)) {
				Billrun_Factory::log($validatorName . " does not exist!");
				continue;
			}

			// Create the validator.
			$validators[] = new $validatorName($fieldConf);
		}
		return $validators;
	}

	/**
	 * Enforce the rules on the data.
	 * @param array $fieldConfiguration - The array of config data
	 * @param array $data The input data to enforce.
	 * @return true or raises an exception.
	 */
	protected function enforce(array $fieldConfiguration, array $data) {
		// Get the validators
		$validators = $this->getFieldValidators($fieldConfiguration);

		$invalidFields = array();
		// Go through the input data.
		foreach ($validators as $fieldName => $validator) {
			$invalidFields += $this->applyValidators($validator, $data);
		}

		// If there are invalid fields, report
		if (!empty($invalidFields)) {
			throw new Billrun_Exceptions_InvalidFields($invalidFields);
		}
		return true;
	}

	/**
	 * Apply validators
	 * @param type $validators
	 * @param type $value
	 * @return type
	 */
	protected function applyValidators($validators, $value) {
		$invalidFields = array();
		foreach ($validators as $validator) {
			$result = $validator->enforce($value);
			if ($result !== true) {
				$invalidFields[] = $result;
			}
		}
		return $invalidFields;
	}

}
