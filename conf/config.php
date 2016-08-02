<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */
$env = null;

if (!defined('APPLICATION_ENV')) {
	defineEnvironment();
}
define('BILLRUN_CONFIG_PATH', APPLICATION_PATH . "/conf/" . APPLICATION_ENV . ".ini");
validateEnvironment();

if (!defined('APPLICATION_TENANT')) {
	$a = getenv('APPLICATION_MULTITENANT');
	$b = php_sapi_name();
	if (getenv('APPLICATION_MULTITENANT') && php_sapi_name() === 'cli') { 
		defineTenant();
	}
}

/**
 * Get a value from the CLI context
 * @return string valu to set up
 */
function getValueFromCLI($opt) {
	if (!is_array($opt)) {
		$opt = arra($opt);
	}
	$envs = getopt('', $opt);
	if (!$envs) {
		return null;
	}
	
	$opts = array_values($envs);
	if (isset($opts[0])) {
		return $opts[0];
	}
	
	return null;
}

/**
 * Define the environment if not yet defined
 */
function defineEnvironment() {
	$env = getenv('APPLICATION_ENV');

	// if APPLICATION_ENV not defined and the getenv not find it (not through web server), let's take it by cli opt
	if (empty($env)) {
		$env = getValueFromCLI(array('env:', 'environment:'));
	}
	
	// Failed to get environment
	if (empty($env)) {
		$errorMessage = 'Environment could not be setup';
		error_log($errorMessage);
		die($errorMessage . PHP_EOL);
	}

	define('APPLICATION_ENV', $env);
}

/**
 * Validate the invironment name
 */
function validateEnvironment() {
	if (file_exists(BILLRUN_CONFIG_PATH)) {
		return;
	}

	$errorMessage = 'Configuration file did not found';
	error_log($errorMessage);
	die($errorMessage);
}

/**
 * Define the tenant if not yet defined
 */
function defineTenant() {
	$tenant = getenv('APPLICATION_TENANT');

	// if APPLICATION_TENANT not defined and the getenv not find it (not through web server), let's take it by cli opt
	if (empty($tenant)) {
		$tenant = getValueFromCLI('tenant');
	}
	
	// Failed to get tenant
	if (empty($tenant)) {
		$errorMessage = 'Tenant could not be setup';
		error_log($errorMessage);
		die($errorMessage . PHP_EOL);
	}

	define('APPLICATION_TENANT', $tenant);
}