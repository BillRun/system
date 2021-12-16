<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Helper class to notify an event
 *
 * @since 5.6
 */
class Billrun_Events_Notifier{

	/**
	 * execute a notification event based on an event received
	 * 
	 * @param array $event
	 * @param array $params
	 * @return mixed- response from notifier on success, false on failure
	 */
	public static function notify($event, $params = array()) {
		$notifier = self::getNotifier($event, $params);
		if (!$notifier) {
			Billrun_Factory::log('Cannot get notifier for event. Details: ' . print_R($event, 1), Billrun_Log::NOTICE);
			return false;
		}
		return $notifier->notify();
	}
	
	/**
	 * Assistance function to get the notifier object based on the event
	 * 
	 * @return notifier object
	 */
	protected static function getNotifier($event, $params = array()) {
		$notifierClassName = self::getNotifierClassName($event, $params);
		if (!class_exists($notifierClassName)) {
			return false;
		}
		
		return (new $notifierClassName($event, $params));
	}

	/**
	 * Assistance function to get notifier name based on event parameters
	 * 
	 * @param array $event
	 * @param array $params
	 * @return string - notifier class name
	 * @todo conclude notifier class from received parameters
	 */
	protected static function getNotifierClassName($event, $params = array()) {
		return 'Billrun_Events_Notifiers_Http';
	}
}
