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
	 * Perform the auto renew process for the given record
	 * 
	 * @param Mongodloid Entity $record
	 * @return true on success, throws exception on error
	 */
	public static function autoRenewRecord($record) {
		$autoRenewHandler = self::getAutoRenewHandler($record);
		return $autoRenewHandler->autoRenew();
	}
	
	/**
	 * Assistance function to get the auto renew handler object
	 * 
	 * @param array $record
	 * @return Billrun_Autorenew_Record
	 */
	public static function getAutoRenewHandler($record) {
		$handlerClassName = self::getAutoRenewHandlerClassName($record);
		if (!class_exists($handlerClassName)) {
			$handlerClassName = 'Billrun_Autorenew_Month';
		}

		return (new $handlerClassName($record));
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
