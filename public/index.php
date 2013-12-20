<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('APPLICATION_PATH') || define("APPLICATION_PATH", dirname(__DIR__));

// TODO: move this into bootstrap
if (!isset($_SERVER['HTTP_USER_AGENT'])) {
	$envOpts = getopt('', array('environment:'));
	$env = isset($envOpts['environment'])?$envOpts['environment']:null;
} else {
	if (!defined('APPLICATION_ENV')) {
		$env = getenv('APPLICATION_ENV');
	}
}

if (!isset($env)) {
	die('Environment did not setup!');
}

define('APPLICATION_ENV', $env);

define('BILLRUN_CONFIG_PATH', APPLICATION_PATH . "/conf/" . APPLICATION_ENV . ".ini");

// TODO build one config ini with declaration for each environment
$app = new Yaf_Application(BILLRUN_CONFIG_PATH);

try {
    $app->bootstrap()->run();
} catch (Exception $e) {
    $log = print_R($_SERVER, TRUE) . PHP_EOL . print_R("Error code : " . $e->getCode() . PHP_EOL . "Error message: " . $e->getMessage() . PHP_EOL . $e->getTraceAsString(), TRUE); // we don't cast the exception to string because Yaf_Exception could cause a segmentation fault
    Billrun_Factory::log()->log("Crashed When running... exception details are as follow : " . PHP_EOL . $log, Zend_Log::CRIT);
}
