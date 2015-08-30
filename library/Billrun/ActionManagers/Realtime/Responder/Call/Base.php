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
	 * The name of the api response
	 * 
	 * @var string 
	 */
	protected $responseApiName = 'basic';

	/**
	 * Create an instance of the RealtimeAction type.
	 */
	public function __construct(array $options = array()) {
		$this->row = $options['row'];
		$this->responseApiName = $this->getResponsApiName();
	}
	
	/**
	 * Sets API name
	 */
	public abstract function getResponsApiName();

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
	 * Gets response message data
	 * 
	 * @return array
	 */
	protected function getResponseData() {
		$ret = array();
		$responseFields = array_merge(Billrun_Factory::config()->getConfigValue('realtimeevent.responseData.basic', array()),
			Billrun_Factory::config()->getConfigValue("realtimeevent.responseData.$this->responseApiName", array()));
		foreach ($responseFields as $responseField => $rowField) {
			if (is_array($rowField)) {
				$ret[$responseField] = (isset($rowField['classMethod']) ? call_user_method($rowField['classMethod'], $this) : '');
			} else {
				$ret[$responseField] = (isset($this->row[$rowField]) ? $this->row[$rowField] : '');
			}
		}
		return $ret;
	}

	/**
	 * Gest the clear casue value, based on $this->row data
	 * 
	 * @return int clear cause value
	 */
	protected function getClearCause() {
		if ($this->row['granted_return_code'] === 0) {
			return Billrun_Factory::config()->getConfigValue('realtimeevent.clearCause.no_balance');
		}
		
		return Billrun_Factory::config()->getConfigValue('realtimeevent.clearCause.normal_release');
	}

}
