<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Helper class to manage the model validators.
 *
 * @since 5.1
 */
class Billrun_TypeValidator_Manager {

	/**
	 * Assistance function to get the validator object based on type name
	 * 
	 * @return typeValidatorClass model validator class
	 */
	public static function getValidator($type) {
		$validatorClassName = self::getValidatorClassName($type);
		if (!class_exists($validatorClassName)) {
			return false;
		}

		return (new $validatorClassName());
	}

	/**
	 * Assistance function to get validator name based on type
	 * 
	 * @param type $type
	 * @return string the name of the class
	 */
	protected static function getValidatorClassName($type) {
		$classNamePref = 'Billrun_TypeValidator_';
		return $classNamePref . ucfirst($type);
	}

}
