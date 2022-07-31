<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Helper class to manage the responders.
 *
 */
class Billrun_Encoder_Manager {

	public static function getEncoder($params) {
		$encoderClassName = self::getEncoderClassName($params);
		if (!class_exists($encoderClassName)) {
			Billrun_Factory::log('Encoder class not found: ' . $encoderClassName, Zend_Log::WARN);
			return false;
		}

		return new $encoderClassName();
	}

	protected static function getEncoderClassName($params) {
		$encoderName = null;

		if (!is_null($encoder = $params['encoder'])) {
			$encoderName = $encoder;
		} else if (!is_null($usaget = $params['usaget'])) {
			$encoderName = Billrun_Factory::config()->getConfigValue(strtolower($usaget) . ".encode");
		} else if (!is_null($controllerName = $params['controllerName']) && !is_null($actionName = $params['actionName'])) {
			$encoderName = Billrun_Factory::config()->getConfigValue(strtolower($controllerName) . ".encode." . strtolower($actionName));
		}
		if (is_null($encoderName)) {
			Billrun_Factory::log('No encoder defined; set to JSON', Zend_Log::WARN);
			$encoderName = 'json';
		}

		return 'Billrun_Encoder_' . ucfirst($encoderName);
	}

}
