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
} catch (Throwable $th) {
	// Executed only in PHP 7, will not match in PHP 5
	$ErrorHandler = new Billrun_Utils_ErrorHandler();
	$ErrorHandler->errorAction($th, true);
} catch (Exception $ex) {
	// Executed only in PHP 5, will not be reached in PHP 7
	$ErrorHandler = new Billrun_Utils_ErrorHandler();
	$ErrorHandler->errorAction(new Exception($ex->getMessage(), 999999));
}