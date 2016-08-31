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
	 * Assistance function to get the validator object based on model name
	 * 
	 * @return modelValidatorClass model validator class
	 */
	public static function getValidator($options = array()) {
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
