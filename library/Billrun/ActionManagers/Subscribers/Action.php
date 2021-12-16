<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a prototype for a subscriber action.
 *
 */
abstract class Billrun_ActionManagers_Subscribers_Action implements Billrun_ActionManagers_IAPIAction {

	use Billrun_ActionManagers_ErrorReporter;
	
	protected $collection = null;
	
	/**
	 * Keeps entity's type (account/subscriber/...)
	 * @var type 
	 */
	protected $type;
	
	protected $fields;

	/**
	 * Create an instance of the SubscibersAction type.
	 */
	public function __construct($params = array()) {
		$this->collection = Billrun_Factory::db()->subscribersCollection();
		$this->baseCode = 1000;
	}

	/**
	 * Parse a request to build the action logic.
	 * 
	 * @param request $request The received request in the API.
	 * @return true if valid.
	 */
	public function parse($request) {
		return $this->init($request);
	}

	/**
	 * Execute the action logic.
	 * 
	 * @return true if sucessfull.
	 */
	public abstract function execute();
	
	protected function init($request) {
		if (!$this->initSubscriberType($request)) {
			return false;
		}
		$this->initFields();
		return true;
	}
	
	protected function initSubscriberType($input) {
		$subscriberTypes = Billrun_Factory::config()->getConfigValue('subscribers.types', array('account', 'subscriber'));
		$type = $input->get('type');
		if (empty($type) ||
			!in_array($type, $subscriberTypes)) {
			$errorCode =  7;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}		
		$this->type = $type;
		return true;
	}
	
	protected function initFields() {
		$this->fields = array_merge(
			Billrun_Factory::config()->getConfigValue("subscribers." . $this->type . ".fields", array()),
			Billrun_Factory::config()->getConfigValue("subscribers.fields", array())
		);
	}

	/**
	* Get the array of fields to be set in the query record from the user input.
	* @return array - Array of fields to set.
	*/
	protected function getQueryFields() {
		return Billrun_Factory::config()->getConfigValue('subscribers.query_fields');
	}	
}
