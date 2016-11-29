<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Helper class to manage the decoders.
 *
 */
class Billrun_Decoder_Manager {

	public static function getDecoder($params) {
		$decoderClassName = self::getDecoderClassName($params);
		if (!class_exists($decoderClassName)) {
			Billrun_Factory::log('Decoder class not found: ' . $decoderClassName, Zend_Log::WARN);
			return false;
		}

		return new $decoderClassName();
	}

	protected static function getDecoderClassName($params) {
		$decoderName = null;

		if (!is_null($decoder = $params['decoder'])) {
			$decoderName = $decoder;
		} else if (!is_null($usaget = $params['usaget'])) {
			$decoderName = Billrun_Factory::config()->getConfigValue(strtolower($usaget) . ".decode");
		} else if (!is_null($controllerName = $params['controllerName']) && !is_null($actionName = $params['actionName'])) {
			$decoderName = Billrun_Factory::config()->getConfigValue(strtolower($controllerName) . ".decode." . strtolower($actionName));
		}
		if (is_null($decoderName)) {
			Billrun_Factory::log('No decoder defined; set to json', Zend_Log::WARN);
			$decoderName = 'json';
		}

		return 'Billrun_Decoder_' . ucfirst($decoderName);
	}

}
