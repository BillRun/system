<?php

/**
 * @package	Billing
 * @copyright	Copyright (C) 2012-2017 BillRun Technologies Ltd. All rights reserved.
 * @license	GNU Affero General Public License Version 4; see LICENSE.txt
 */

/**
 * helper to get auto renew for a single record
 *
 */
class Billrun_Autorenew_Manager {
	
	/**
	 * get Auto renew handler instance
	 * 
	 * @param array $params the parameters of the action
	 * 
	 * @return AutoRenew handler
	 */
	public static function getInstance($params) {
		$handlerClassName = self::getAutoRenewHandlerClassName($params);
		if (!@class_exists($handlerClassName, true)) {
			$handlerClassName = 'Billrun_Autorenew_Month';
		}

		return (new $handlerClassName($params));
	}

	/**
	 * Assistance function to get record class name
	 * 
	 * @param array $record
	 * @return string the name of the class
	 */
	protected static function getAutoRenewHandlerClassName($record) {
		$classNamePref = 'Billrun_Autorenew_';
		return $classNamePref . ucfirst($record['interval']);
	}
}
