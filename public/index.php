<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('APPLICATION_PATH') || define("APPLICATION_PATH", dirname(__DIR__));

require_once APPLICATION_PATH . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'config.php';

$app = new Yaf_Application(BILLRUN_CONFIG_PATH);

// TODO: move this into bootstrap
if (!isset($_SERVER['HTTP_USER_AGENT'])) {
	$app->getDispatcher()->setDefaultController('Cli');
}
try {
	$app->bootstrap()->run();
} catch (Exception $e) {
	$log = print_R($_SERVER, TRUE) . PHP_EOL . print_R($e, TRUE);
	Billrun_Factory::log()->log("Crashed When running... exception details are as follow : " . PHP_EOL . $log , Zend_Log::CRIT);
}
