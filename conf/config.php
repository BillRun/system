<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */
$env = null;
if (isset($_SERVER['HTTP_USER_AGENT'])) {
	// browser
	if (!defined('APPLICATION_ENV')) {
		$env = getenv('APPLICATION_ENV');
	}
} else {	
	// command line
	$envOpts = array_values(getopt('', array('env::', 'environment::')));
	if (isset($envOpts[0])) {
		$env = $envOpts[0];
	}
}

if (is_null($env)) {
	die('Environment did not setup!' . PHP_EOL);
}

define('APPLICATION_ENV', $env);

define('BILLRUN_CONFIG_PATH', APPLICATION_PATH . "/conf/" . APPLICATION_ENV . ".ini");

if (!file_exists(BILLRUN_CONFIG_PATH)) {
	die('Configuration file did not found');
}
