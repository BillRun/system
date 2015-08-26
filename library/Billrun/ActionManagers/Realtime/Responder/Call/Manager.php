<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Helper class to manage the responders.
 *
 * @author tom
 */
class Billrun_ActionManagers_Realtime_Responder_Call_Manager {

	/**
	 * Send respond base on data received
	 * 
	 * @param type $data
	 * @return boolean true for success, false otherwise
	 */
	public static function respond($data) {
		$responder = self::getResponderObject($data);
		if (!$responder) {
			return false;
		}
		
		$response = $responder->getResponse();
		//TODO: send response
		return true;
	}
	
	/**
	 * Get response message base on data received
	 * 
	 * @param type $data
	 * @return boolean true for success, false otherwise
	 */
	public static function getResponse($data) {
		$responder = $this->getResponderObject($data['record_type']);
		if (!$responder) {
			return false;
		}
		
		return $responder->getResponse();
	}
	
	/**
	 * Assistance function to get responder object based on response type
	 * 
	 * @param type $data
	 * @return responderClass responder class
	 */
	protected static function getResponderObject($data) {
		$responderClassName = self::getResponderClassName($data['record_type']);
		if (!class_exists($responderClassName)) {
			Billrun_Factory::log("Could not send respond. class $responderClassName not exists. Data:" . print_r($data, 1), Zend_Log::ALERT);
			return false;
		}
		
		return (new $responderClassName($data));
	}
	
	/**
	 * Assistance function to get responder object name based on response type
	 * 
	 * @param type $recordType
	 * @return string the name of the class
	 */
	protected static function getResponderClassName($recordType) {
		$classNamePref = 'Billrun_ActionManagers_Realtime_Responder_Call_';
		return $classNamePref . str_replace(" ","", ucwords(str_replace("_", " ", $recordType)));
	}
}
