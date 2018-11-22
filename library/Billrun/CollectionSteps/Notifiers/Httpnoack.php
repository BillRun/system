<?php

/**
 * 
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Collect HTTP Notifier
 *
 * @package  Billing
 * @since    5.0
 */
class Billrun_CollectionSteps_Notifiers_Httpnoack extends Billrun_CollectionSteps_Notifiers_Http {
	
	/**
	 * parse the response received from the request
	 * @return mixed
	 */
	protected function parseResponse($response) {
		return $response;
	}
	
	/**
	 * checks if the response from request is valid
	 * 
	 * @param array $response
	 * @return boolean
	 */
	protected function isResponseValid($response) {
		return true;
	}
	
}
