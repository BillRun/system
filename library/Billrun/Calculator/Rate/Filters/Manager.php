<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing manager for rate filters
 *
 * @package  calculator
 * @since braas
 */
class Billrun_Calculator_Rate_Filters_Manager {
	
	public static function getFilterHandler($filter) {
		$className = 'Billrun_Calculator_Rate_Filters_' . ucfirst($filter['type']);
		if (!class_exists($className)) {
			return false;
		}
		return new $className($filter);
	}
}
