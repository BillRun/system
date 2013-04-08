<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * this file configured the environments and configuration of each one
 * if you have only one environment please set it directly as follow:
 * 
 * define('BILLRUN_CONFIG_PATH', APPLICATION_PATH . "/conf/configuration.ini");
 * 
 *  * put the above line in the head of this file and then create configuration.ini in application/conf directory
 * all the below code will be ignored
 */

if (!defined('BILLRUN_CONFIG_PATH')) {
	$config = array(
		'servers' => array(
			'dev' => array('127.0.0.1', '127.0.1.1', '::1'),
			'test' => array(),
			'prod' => array('192.168.37.10', 'fraud.golan.local', '192.168.30.20'),
		)
	);

	$envOpts = getopt('',array('environment:'));
	if (isset($envOpts['environment'])) {
		$config_env = (string) $envOpts['environment'];
	} else {
		$current_server = gethostbyname(gethostname()); // we cannot use $_SERVER cause we are on CLI on most cases
		foreach ($config['servers'] as $key => $server) {
			if ( is_array($server) && in_array($current_server, $server) ||
				 $current_server == $server ) {
					$config_env = $key;
				}	
		}
	}

	$conf_path = APPLICATION_PATH . "/conf/" . $config_env . ".ini";
	if (!file_exists($conf_path)) {
		die("no config file found" . PHP_EOL);
	}

	define('BILLRUN_CONFIG_PATH', $conf_path);
}
