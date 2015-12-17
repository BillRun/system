<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Prepaid plugin
 *
 * @package  Application
 * @subpackage Plugins
 * @since    0.5
 */
class prepaidPlugin extends Billrun_Plugin_BillrunPluginBase {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'prepaid';
	
	/**
	 * Method to trigger api outside of Billrun.
	 * afterSubscriberBalanceNotFound trigger after the subscriber has no available balance (relevant only for prepaid subscribers)
	 * 
	 * @param array $row the line from lines collection
	 * 
	 * @return boolean true for success, false otherwise
	 * 
	 */
	public function afterSubscriberBalanceNotFound($row) {
		return false; // TODO: temporary, disable send of clear call
		return self::sendClearCallRequest($row);
	}
	
	/**
	 * Send a request of ClearCall
	 * 
	 * @param type $row
	 * @return boolean true for success, false otherwise
	 */
	protected static function sendClearCallRequest($row) {
		$encoder = Billrun_Encoder_Manager::getEncoder(array(
			'usaget' => $row['usaget']
		));
		if (!$encoder) {
			Billrun_Factory::log('Cannot get encoder', Zend_Log::ALERT);
			return false;
		}
		
		$row['record_type'] = 'clear_call';
		$responder = Billrun_ActionManagers_Realtime_Responder_Manager::getResponder($row);
		if (!$responder) {
			Billrun_Factory::log('Cannot get responder', Zend_Log::ALERT);
			return false;
		}

		$request = array($encoder->encode($responder->getResponse(), "request"));
		// Sends request
		$requestUrl = Billrun_Factory::config()->getConfigValue('IN.request.url.realtimeevent');
		return Billrun_Util::sendRequest($requestUrl, $request);
	}
}
