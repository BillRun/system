<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */
$env = null;

if (!defined('APPLICATION_ENV')) {
	$env = getenv('APPLICATION_ENV');
	
	// if APPLICATION_ENV not defined and the getenv not find it (not through web server), let's take it by cli opt
	if (empty($env) && ($envs = getopt('', array('env:', 'environment:')))) {
		$envOpts = array_values($envs);
		if (isset($envOpts[0])) {
			$env = $envOpts[0];
		}
	}

	if (empty($env)) {
		error_log('Environment did not setup!');
		die('Environment did not setup!' . PHP_EOL);
	}
	
	define('APPLICATION_ENV', $env);
}

define('BILLRUN_CONFIG_PATH', APPLICATION_PATH . "/conf/" . APPLICATION_ENV . ".ini");

if (!file_exists(BILLRUN_CONFIG_PATH)) {
	error_log('Configuration file did not found');
	die('Configuration file did not found');
}
