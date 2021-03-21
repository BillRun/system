<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billrun Test entry class
 *
 * @package  Controller
 * @since    4.4
 */
class Billrun_Test {
	
    public static function getInstance($action) {
		$path = APPLICATION_PATH . '/library/Tests/' . ucfirst($action) . '.php';
		if (file_exists($path)) {
			require_once $path;
		}
    }

}
