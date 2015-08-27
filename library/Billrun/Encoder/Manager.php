<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Helper class to manage the responders.
 *
 */
class Billrun_Encoder_Manager {

	public static function getEncoder($controllerName, $actionName) {
		$encoderClassName = self::getEncoderClassName($controllerName, $actionName);
		if (!class_exists($encoderClassName)) {
			Billrun_Factory::log('Encoder class not found: ' . $encoderClassName, Zend_Log::NOTICE);
			return false;
		}

		return new $encoderClassName();
	}

	protected static function getEncoderClassName($controllerName, $actionName) {
		$encoderName = Billrun_Factory::config()->getConfigValue(strtolower($controllerName) . ".encode." . strtolower($actionName));
		if (is_null($encoderName)) {
			Billrun_Factory::log('No output decode defined; set to array', Zend_Log::DEBUG);
			$encoderName = 'array';
		} else {
			$encoderName = $encoderName;
		}

		return 'Billrun_Encoder_' . ucfirst($encoderName);
	}

}
