<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */
define('RUNNING_FROM_CLI', php_sapi_name() === 'cli');

$env = null;
$cliArgs = getopt('', array('env:', 'environment:', 'tenant:'));

if (!defined('APPLICATION_ENV')) {
	$env = getenv('APPLICATION_ENV');

	// if APPLICATION_ENV not defined and the getenv not find it (not through web server), let's take it by cli opt
	if (empty($env)) {
		if (isset($cliArgs['env'])) {
			$env = $cliArgs['env'];
		} else if (isset($cliArgs['environment'])) {
			$env = $cliArgs['environment'];
		}
	}

	if (empty($env)) {
		die('Environment did not setup!' . PHP_EOL);
	}

	define('APPLICATION_ENV', $env);
}

define('BILLRUN_CONFIG_PATH', APPLICATION_PATH . "/conf/" . APPLICATION_ENV . ".ini");

if (!file_exists(BILLRUN_CONFIG_PATH)) {
	error_log('Configuration file ' . BILLRUN_CONFIG_PATH . ' was not found.');
	die('Configuration file was not found');
}

if (RUNNING_FROM_CLI && getenv('APPLICATION_MULTITENANT') && !defined('APPLICATION_TENANT')) {
	if (empty($cliArgs['tenant'])) {
		die('Tenant was not setup!' . PHP_EOL);
	}
	
	define('APPLICATION_TENANT', $cliArgs['tenant']);
}

if (!defined('APPLICATION_MULTITENANT') && $multitenant = getenv('APPLICATION_MULTITENANT')) {
	define('APPLICATION_MULTITENANT', $multitenant);
}