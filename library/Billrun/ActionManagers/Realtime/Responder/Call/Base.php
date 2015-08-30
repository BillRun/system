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
abstract class Billrun_ActionManagers_Realtime_Responder_Call_Base {

	/**
	 * Db line to create the response from
	 * 
	 * @var type array
	 */
	protected $row;

	/**
	 * Create an instance of the RealtimeAction type.
	 */
	public function __construct(array $options) {
		
		$this->row = $options['row'];
	}
	
	/**
	 * Checks if the responder is valid
	 * 
	 * @return boolean
	 */
	public function isValid() {
		return (!is_null($this->row));
	}
	
	/**
	 * Get response message
	 */
	public function getResponse() {
		$responseData = $this->getResponseData();
		return $responseData;
	}
	
	/**
	 * Get response message data
	 */
	public abstract function getResponseData();
	
	/**
	 * Gets response message basic data (shared to most responses)
	 * 
	 * @return array
	 */
	protected function getResponseBasicData() {
		$grantedReturnCode = $this->row['granted_return_code'];
		return array(
			'CallingNumber' => $this->row['calling_number'],
			'CallReference' => $this->row['call_reference'],
			'CallID' => $this->row['call_id'],
			'ReturnCode' => $grantedReturnCode,
			'ClearCause' => ($grantedReturnCode === 0 ? 1 : 0), //TODO: check if it's correct value
		);
	}
}
