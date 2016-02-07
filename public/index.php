<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
defined('APPLICATION_PATH') || define('APPLICATION_PATH', dirname(__DIR__));

//class A {
//
//	protected $shani = array('a' => 1, 'b' => 2);
//
//		/**
//	 * method to receive the items that the processor parsed on each iteration of parser
//	 * 
//	 * @return array items 
//	 */
//	public function &getShani() {
//		return $this->shani;
//	}
//	
//	public function unsetVars($key) {
//		unset($this->shani[$key]);
//	}
//
//}
//
//$a = new A();
//$shani = &$a->getShani();
//foreach ($shani as $key => &$value) {
//	$a->unsetVars($key);
//	echo 'aaa';
//}



require_once(APPLICATION_PATH . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'config.php');

$app = new Yaf_Application(BILLRUN_CONFIG_PATH);

try {
	$app->bootstrap()->run();
} catch (Exception $e) {
	try {
		Billrun_Factory::log()->logCrash($e);
	} catch(Exception $e1) {
		print_r("Log error! " . print_r($e1, 1));
	}
}
