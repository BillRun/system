<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('APPLICATION_PATH')
	|| define("APPLICATION_PATH", dirname(__DIR__));

$conf_path = APPLICATION_PATH . "/conf/application.ini";
$app = new Yaf_Application($conf_path);

if (!isset($_SERVER['HTTP_USER_AGENT'])) {
	$app->getDispatcher()->setDefaultAction('Cli');
}
$app->bootstrap()->run();
