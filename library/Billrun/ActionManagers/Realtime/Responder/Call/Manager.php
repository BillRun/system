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
class Billrun_ActionManagers_Realtime_Responder_Call_Manager {

	/**
	 * Assistance function to get responder object based on response type
	 * 
	 * @param type $data
	 * @return responderClass responder class
	 */
	public static function getResponder($data) {
		$responderClassName = self::getResponderClassName($data['record_type']);
		if (!class_exists($responderClassName)) {
			Billrun_Factory::log("Could not send respond. class $responderClassName not exists. Data:" . print_r($data, 1), Zend_Log::ALERT);
			return false;
		}

		return (new $responderClassName(array('row' => $data)));
	}

	/**
	 * Assistance function to get responder object name based on response type
	 * 
	 * @param type $recordType
	 * @return string the name of the class
	 */
	protected static function getResponderClassName($recordType) {
		$classNamePref = 'Billrun_ActionManagers_Realtime_Responder_Call_';
		return $classNamePref . str_replace(" ", "", ucwords(str_replace("_", " ", $recordType)));
	}

}
