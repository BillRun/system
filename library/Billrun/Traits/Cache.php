<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing trait cache class to help cache class methods results
 *
 * @package  Traits
 * @since    4.6
 */
Trait Billrun_Traits_Cache {

	/**
	 * method to cache class method results (if cache enabled)
	 * 
	 * @param Object $obj object
	 * @param string $methodName method name
	 * @param array $args method arguments
	 * 
	 * @return mixed method return value
	 */
	protected function getSetCache($obj, $methodName, $args, $cacheKey = null, $cachePrefix = null) {
		$cache = Billrun_Factory::cache();
		if (!$cache) {
			return call_user_func_array(array($obj, $methodName), $args);
		}

		if (is_null($cacheKey)) {
			$cacheKey = Billrun_Util::generateArrayStamp($args);
		}
		if (is_null($cachePrefix)) {
			$cachePrefix = get_class($obj) . '_' . strtolower($methodName);
		}
		$cachedData = $cache->get($cacheKey, $cachePrefix);
		if (!is_null($cachedData) && $cachedData !== FALSE) {
			return $cachedData;
		}

		$ret = call_user_func_array(array($obj, $methodName), $args);
		$cache->set($cacheKey, $ret, $cachePrefix, strtotime('tomorrow') - 1);
		return $ret;
	}

}
