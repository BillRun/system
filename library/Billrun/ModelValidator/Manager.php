<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Helper class to manage the model validators.
 *
 */
class Billrun_ModelValidator_Manager {

	/**
	 * Gets a validator, and validate the data received
	 * 
	 * @param type $data to validate
	 * @param type $validatorOptions - 
	 * @return array of: 
	 * 					"validate" - true/false
	 * 					"errorMsg" - error message
	 */
	public static function validate($data, $type, $validatorOptions = array()) {
		$validator = self::getValidator($validatorOptions);
		return $validator->validate($data, $type);
	}

	/**
	 * Assistance function to get the validator object based on model name
	 * 
	 * @return modelValidatorClass model validator class
	 */
	protected static function getValidator($options = array()) {
		$modelName = $options['modelName'];
		$validatorClassName = self::getValidatorClassName($modelName, $options);
		if (!class_exists($validatorClassName)) {
			$validatorClassName = 'Billrun_ModelValidator_Base';
		}

		return (new $validatorClassName($options));
	}

	/**
	 * Assistance function to get validatorname based on model name
	 * 
	 * @param type $modelName
	 * @return string the name of the class
	 */
	protected static function getValidatorClassName($modelName, $options) {
		$classNamePref = 'Billrun_ModelValidator_';
		return $classNamePref . ucfirst($modelName);
	}

}
