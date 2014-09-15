<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Api abstract action class
 *
 * @package  Action
 * @since    0.8
 */
abstract class ApiAction extends Action_Base {

	function setError($error_message, $input = null) {
		Billrun_Factory::log()->log("Sending Error : {$error_message}", Zend_Log::NOTICE);
		$output = array(
			'status' => 0,
			'desc' => $error_message,
		);
		if (!is_null($input)) {
			$output['input'] = $input;
		}
		$this->getController()->setOutput(array($output));
		return;
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
		$actionName = Yaf_Dispatcher::getInstance()->getRequest()->getActionName();
		$cachePrefix = $actionName . '_';
		$cacheKey = Billrun_Util::generateArrayStamp(array_values($params['stampParams']));
		$cachedData = $cache->get($cacheKey, $cachePrefix);
		if (empty($cachedData)) {
			$cachedData = $this->fetchData($params['fetchParams']);
			$cache->set($cacheKey, $cachedData, $cachePrefix);
		} else {
			Billrun_Factory::log()->log("Fetch data from cache for " . $actionName . " api call", Zend_Log::INFO);
		}
		return $cachedData;
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

}
