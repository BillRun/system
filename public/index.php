<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('APPLICATION_PATH')
	|| define("APPLICATION_PATH", dirname(__DIR__));

require_once APPLICATION_PATH . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'config.php';

$app = new Yaf_Application(BILLRUN_CONFIG_PATH);

if (!isset($_SERVER['HTTP_USER_AGENT'])) {
	$app->getDispatcher()->setDefaultController('Cli');
}
try {
	$app->bootstrap()->run();
} catch(Exception $e){
	Billrun_Factory::log()->log("Crashed When running... exception details are as follow : \n".print_r($e,1), Zend_Log::CRIT);			
}
