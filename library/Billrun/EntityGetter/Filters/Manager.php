<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing manager for filters
 *
 * @package  calculator
 * @since 5.10
 */
class Billrun_EntityGetter_Filters_Manager {
	
	public static function getFilterHandler($filter) {
		$className = 'Billrun_EntityGetter_Filters_' . ucfirst($filter['type']);
		if (!class_exists($className)) {
			return false;
		}
		return new $className($filter);
	}
}
