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
class Billrun_ActionManagers_Realtime_Responder_Manager {

	/**
	 * Assistance function to get responder object based on response type
	 * 
	 * @param type $data
	 * @return responderClass responder class
	 */
	public static function getResponder($params) {
		$data = $params['data'];
		$config = $params['config'];
		$responderClassName = self::getResponderClassName($data);
		if (!class_exists($responderClassName)) {
			Billrun_Factory::log("Could not send respond. class $responderClassName does not exist. Data:" . print_r($data, 1), Zend_Log::ERR);
			return false;
		}

		return (new $responderClassName(array('row' => $data, 'config' => $config)));
	}

	/**
	 * Assistance function to get responder object name based on response type
	 * 
	 * @param type $data
	 * @return string the name of the class
	 */
	protected static function getResponderClassName($data) {
		$classNamePref = 'Billrun_ActionManagers_Realtime_Responder_Realtime_';
		$className = $classNamePref . str_replace(" ", "", ucwords(str_replace("_", " ", $data['record_type'])));
		$defaultClassName = $classNamePref . 'Base';
		return @class_exists($className) ? $className : $defaultClassName;
	}

}
