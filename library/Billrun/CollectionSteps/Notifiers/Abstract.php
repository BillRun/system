<?php

/**
 * 
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Collect Step Notifier
 *
 * @package  Billing
 * @since    5.0
 */
abstract class Billrun_CollectionSteps_Notifiers_Abstract implements Billrun_CollectionSteps_Notifiers_Strategy {

	/**
	 * saves general settings for collection
	 */
	protected $settings = array();

	/**
	 * saves current task
	 */
	protected $task = array();

	public function __construct($task) {
		$this->task = $task;
		$this->settings = $this->getSettings();
	}

	abstract protected function run();

	public function notify() {
		try {
			$response = $this->parseResponse($this->run());
			if ($this->isResponseValid($response)) {
				Billrun_Factory::log('Collection steps notifier finished successfully with response: ' . print_r($response, 1), Zend_Log::DEBUG);
				return $this->getSuccessResponse($response);
			}
		} catch (Exception $exc) {
			Billrun_Factory::log('Collection steps notifier crashed with error: ' . $exc->getMessage(), Zend_Log::ERR);
		}
		Billrun_Factory::log('Collection steps notifier run failed for task: ' . print_r($this->task, 1), Zend_Log::WARN);
		return $this->getFailureResponse();
	}

	/**
	 * gets the relevant settings for the notifier
	 * 
	 * @return type
	 */
	protected function getSettings() {
		return array_merge(
				Billrun_Factory::config()->getConfigValue('collection.settings', array())
		);
	}

	/**
	 * parse the response received after run
	 * @return mixed
	 */
	protected function parseResponse($response) {
		return $response;
	}

	/**
	 * checks if the response is valid
	 * 
	 * @param array $response
	 * @return boolean
	 */
	protected function isResponseValid($response) {
		return true;
	}

	/**
	 * build a response to send in case of response received from the request
	 * 
	 * @param mixed $response
	 * @return mixed
	 */
	protected function getSuccessResponse($response) {
		return $response;
	}

	/**
	 * build a response to send in case no response received from the request
	 * @return mixed
	 */
	protected function getFailureResponse($response = false) {
		return false;
	}

	protected function getAccount($aid) {
		$billrunAaccount = Billrun_Factory::account();
		$billrunAaccount->loadAccountForQuery(array('aid' => $aid));
		return $billrunAaccount;
	}

	protected function updateDynamicData($string, $params) {
		$replaced_string = Billrun_Factory::templateTokens()->replaceTokens($string, $params);
		return $replaced_string;
	}

	protected function getAid() {
		return $this->task['extra_params']['aid'];
	}

}
