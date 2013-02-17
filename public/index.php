<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('APPLICATION_PATH')
	|| define("APPLICATION_PATH", dirname(__DIR__));

$config = array(
	'servers' => array(
		'dev' => array('127.0.0.1', '127.0.1.1', '::1'),
		'test' => array('192.168.36.10'),
		'prod' => array('192.168.37.10'),
	)
);

$current_server = gethostbyname(gethostname()); // we cannot use $_SERVER cause we are on CLI on most cases
foreach ($config['servers'] as $key => $server) {
	if (is_array($server) && in_array($current_server, $server)) {
		$config_env = $key;
		break;
	} elseif ($current_server == $server) {
		$config_env = $key;
		break;
	}
}

$conf_path = APPLICATION_PATH . "/conf/" . $config_env . ".ini";
if (!file_exists($conf_path)) {
	die("no config file found" . PHP_EOL);
}
$app = new Yaf_Application($conf_path);

if (!isset($_SERVER['HTTP_USER_AGENT'])) {
	$app->getDispatcher()->setDefaultAction('Cli');
}
$app->bootstrap()->run();
