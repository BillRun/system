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
	 * Get the collection for the current field validator.
	 */
	abstract protected function _getCollection();
	
	/**
	 * The base query to be used to verify unique values.
	 * @return type
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
			if(!$fieldName) {
				Billrun_Factory::log("Corrupted config data: " . print_r($currentConfiguration,1), Zend_Log::NOTICE);
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
	 * Get all the validators for a field.
	 * @param array $fieldConf - Field configuration (from the config collection).
	 * @return array of rule enforcers
	 */
	protected function getValidatorsForField(array $fieldConf) {
		$validators = array();
		foreach ($fieldConf as $key => $value) {
			// If the value is false skip the enforcer.
			if(!$value) {
				continue;
			}
			// Construct the validator's name
			// TODO: Move this magic to a const.
			$validatorName = "Billrun_DataTypes_FieldEnforcer_" . ucfirst(strtolower($key));
			
			// Check if the validator exists.
			if(!@class_exists($validatorName)) {
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
		foreach ($data as $key => $value) {
			// Check if there are enforcers for the input field.
			if(isset($validators[$key])) {
				// Apply the validators.
				$invalidFields []= $this->applyValidators($validators[$key], $data);
			}
		}
		
		// If there are invalid fields, report
		if($invalidFields) {
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
			if($result !== true) {
				$invalidFields[] = $result;
			}
		}
		return $invalidFields;
	}
	
}
