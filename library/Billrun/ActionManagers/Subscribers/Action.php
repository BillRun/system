<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a prototype for a subscriber action.
 *
 */
abstract class Billrun_ActionManagers_Subscribers_Action extends Billrun_ActionManagers_APIAction {

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
	public function __construct($params) {
		$this->collection = Billrun_Factory::db()->subscribersCollection();
		Billrun_Factory::config()->addConfig(APPLICATION_PATH . "/conf/subscribers/errors.ini");
		parent::__construct($params);
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
		$subscriberTypes = Billrun_Factory::config()->getConfigValue('subscribers.types', array());
		if (empty($this->type = $input->get('type')) ||
			!in_array($this->type, $subscriberTypes)) {
			$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base") + 7;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}		
		return true;
	}
	
	protected function initFields() {
		$this->fields = array_merge(
			Billrun_Factory::config()->getConfigValue("subscribers." . $this->type . ".fields", array()),
			Billrun_Factory::config()->getConfigValue("subscribers.fields", array())
		);
	}

}
