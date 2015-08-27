<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Helper class to manage the decoders.
 *
 */
class Billrun_Decoder_Manager {

	public static function getDecoder($controllerName, $actionName) {
		$decoderClassName = self::getDecoderClassName($controllerName, $actionName);
		if (!class_exists($decoderClassName)) {
			Billrun_Factory::log('Decoder class not found: ' . $decoderClassName, Zend_Log::NOTICE);
			return false;
		}
		
		return new $decoderClassName();
	}
	
	protected static function getDecoderClassName($controllerName, $actionName) {
		$decoderName = Billrun_Factory::config()->getConfigValue(strtolower($controllerName) . ".decode." . strtolower($actionName));
		if (is_null($decoderName)) {
			$decoderName = 'json';
			Billrun_Factory::log('No output decode defined; set to json', Zend_Log::DEBUG);
		} else {
			$decoderName = $decoderName;
		}
		
		return 'Billrun_Decoder_' . ucfirst($decoderName);
	}

}
