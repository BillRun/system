<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Api abstract action class
 *
 * @package  Action
 * @since    0.8
 */
abstract class ApiAction extends Action_Base {

	protected $cors = true;

	/**
	 * initialize API action (run on constructor)
	 */
	public function init() {
		// this will extend session timeout
		Billrun_Util::setHttpSessionTimeout();
		if ($this->cors) {
			Billrun_Utils_Security::openCrossDomain();
		}
	}

	/**
	 * how much time to store in cache (seconds)
	 * 
	 * @var int
	 */
	protected $cacheLifetime = 14400;

	/**
	 * Set an error message to the controller.
	 * @param string $errorMessage - Error message to send to the controller.
	 * @param object $input - The input the triggerd the error.
	 * @return ALWAYS false.
	 */
	function setError($errorMessage, $input = null) {
		Billrun_Factory::log("Sending Error : {$errorMessage}", Zend_Log::NOTICE);
		$output = array(
			'status' => 0,
			'desc' => $errorMessage,
		);
		if (!is_null($input)) {
			$output['input'] = $input;
		}

		// Throwing a general exception.
		// TODO: Debug default code
		$ex = new Billrun_Exceptions_Api(999, array(), $errorMessage);
		throw $ex;

		// If failed to report to controller.
		if (!$this->getController()->setOutput(array($output))) {
			Billrun_Factory::log("Failed to set message to controller. message: " . $errorMessage, Zend_Log::CRIT);
		}

		return false;
	}
	
	
	/**
	 * set a response for a successful response to the controller
	 * 
	 * @param array $details
	 * @param array $input
	 * @param string $desc
	 */
	function setSuccess($details = null, $input = null, $desc = 'success') {
		$output = [
			'status' => 1,
			'desc' => $desc,
		];

		if (!is_null($details)) {
			$output['details'] = $details;
		}

		if (!is_null($input)) {
			$output['input'] = $input;
		}

		$this->getController()->setOutput(array($output));
	}

	/**
	 * method to store and fetch by global cache layer
	 * 
	 * @param type $params params to be used by cache to populate and store
	 * 
	 * @return mixed the cached results
	 */
	protected function cache($params) {
		if (!isset($params['stampParams'])) {
			$params['stampParams'] = $params['fetchParams'];
		}
		$cache = Billrun_Factory::cache();
		if (empty($cache)) {
			return $this->fetchData($params['fetchParams']);
		}
		$actionName = $this->getAction();
		$cachePrefix = $this->getCachePrefix();
		$cacheKey = Billrun_Util::generateArrayStamp(array_values($params['stampParams']));
		$cachedData = $cache->get($cacheKey, $cachePrefix);
		if (!empty($cachedData)) {
			Billrun_Factory::log("Fetch data from cache for " . $actionName . " api call", Zend_Log::INFO);
		} else {
			$cachedData = $this->fetchData($params['fetchParams']);
			$lifetime = Billrun_Factory::config()->getConfigValue('api.cacheLifetime.' . $actionName, $this->getCacheLifeTime());
			$cache->set($cacheKey, $cachedData, $cachePrefix, $lifetime);
		}

		return $cachedData;
	}

	/**
	 * method to get cache prefix of this action
	 * 
	 * @return string
	 */
	protected function getCachePrefix() {
		return $this->getAction() . '_';
	}

	/**
	 * method to get controller action name
	 * 
	 * @return string
	 */
	protected function getAction() {
		return Yaf_Dispatcher::getInstance()->getRequest()->getActionName();
	}

	/**
	 * basic fetch data method used by the cache
	 * 
	 * @param array $params parameters to fetch the data
	 * 
	 * @return boolean
	 */
	protected function fetchData($params) {
		return true;
	}

	/**
	 * method to set api call cache lifetime
	 * @param int $val the cache lifetime (seconds)
	 */
	protected function setCacheLifeTime($val) {
		$this->cacheLifetime = $val;
	}

	/**
	 * method to get api call cache lifetime
	 * @return int $val the cache lifetime (seconds)
	 */
	protected function getCacheLifeTime() {
		return $this->cacheLifetime;
	}

	/**
	 * render override to handle HTTP 1.0 requests
	 * 
	 * @param string $tpl template name
	 * @param array $parameters view parameters
	 * @return string output
	 */
	protected function render($tpl, array $parameters = null) {
		$ret = parent::render($tpl, $parameters);
		if ($this->getRequest()->get('SERVER_PROTOCOL') == 'HTTP/1.0' && !is_null($ret) && is_string($ret)) {
			header('Content-Length: ' . strlen($ret));
		}
		return $ret;
	}

}
