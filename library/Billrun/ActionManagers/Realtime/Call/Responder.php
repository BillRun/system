<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a prototype for a Realtime response action.
 *
 */
abstract class Billrun_ActionManagers_Realtime_Call_Responder {

	protected $row;
	protected $controller;


	/**
	 * Create an instance of the RealtimeAction type.
	 */
	public function __construct($row, $controller = null) {
		$this->row = $row;
		$this->controller = $controller;
	}
	
	/**
	 * Get response message
	 */
	public function getResponse() {
		$responseData = $this->getResponseData();
		if (!is_null($this->controller)) {
			return $this->controller->setOutput($responseData);
		}
		return $responseData;
	}
	
	/**
	 * Get response message data
	 */
	public abstract function getResponseData();
}
