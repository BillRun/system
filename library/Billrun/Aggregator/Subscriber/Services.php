<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Subscriber services aggregator.
 *
 * @package  Aggregator
 * @since    5.1
 */
class Billrun_Aggregator_Subscriber_Services extends Billrun_Aggregator_Subscriber_Base {
	
	/**
	 * Handle an exception in the save function.
	 * @param Exception $e
	 * @param type $subscriber
	 * @param type $billrunKey
	 * @param type $rawData
	 * @return type
	 */
	protected function handleException(Exception $e, $subscriber, $billrunKey, $rawData) {
		if(!parent::handleException($e, $subscriber, $billrunKey, $rawData)) {
			return false;
		}
		
		Billrun_Factory::log("Problem inserting service for subscriber " . $subscriber->sid . " for billrun " . $billrunKey
			. ". error message: " . $e->getMessage() . ". error code: " . $e->getCode() . ". service details:" . print_R($rawData, 1), Zend_Log::ALERT);
		Billrun_Util::logFailedServiceRow($rawData);
	}
	
	/**
	 * Get the services
	 * @param type $billrunKey
	 * @param type $subscriber
	 * @return type
	 */
	protected function getData($billrunKey, $subscriber) {
		return array();
	}
}
