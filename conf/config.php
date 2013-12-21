<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

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

if (!file_exists(BILLRUN_CONFIG_PATH)) {
	die('Configuration file did not found');
}
