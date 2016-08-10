<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
defined('APPLICATION_PATH') || define('APPLICATION_PATH', dirname(__DIR__));

require_once(APPLICATION_PATH . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'config.php');
$app = new Yaf_Application(BILLRUN_CONFIG_PATH);

try {
	$app->bootstrap()->run();
} catch (Exception $e) {
	try {
		Billrun_Factory::log()->logCrash($e);
	} catch(Exception $e1) {
		print_r("Log error! " . print_r($e1, 1));
	}
}
