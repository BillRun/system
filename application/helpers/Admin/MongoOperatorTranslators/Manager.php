<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Helper class to manage the mongo operator translators.
 *
 */
class Admin_MongoOperatorTranslators_Manager {

	static $updaterTranslator = array('starts_with' => 'StartsWith',
		'ends_with' => 'EndsWith',
		'like' => 'Like',
		'lt' => 'LessThan',
		'lte' => 'LessThanEqual',
		'gt' => 'GreaterThan',
		'gte' => 'GreaterThanEqual',
		'ne' => 'NotEqual',
		'equals' => 'Equal');

	/**
	 * This function receives oprator name and returns an updater.
	 * @param type $operator
	 * @return type admin mongo operator translator
	 */
	public static function getUpdater($operator) {
		if (!isset(self::$updaterTranslator[$operator])) {
			// This is not an error!
			return null;
		}

		$updater = self::$updaterTranslator[$operator];

		$actionClass = str_replace('_Manager', "_$updater", __CLASS__);
		$action = new $actionClass();

		if (!$action) {
			Billrun_Factory::log("getUpdater Action '$updater' is invalid!", Zend_Log::INFO);
			return null;
		}

		return $action;
	}

}
