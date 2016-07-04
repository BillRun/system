<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing test controller class
 *
 * @package  Controller
 * @since    4.4
 */
class TestController extends Yaf_Controller_Abstract {

	/**
	 * tests to be run
	 * @var array
	 */
	protected $tests = array();


	public function init() {
		if (Billrun_Factory::config()->isProd()) {
			die("Cannot run on production environment");
		}
//		print_r($this->getRequest());die;
		Billrun_Test::getInstance($this->getRequest()->action);
		$this->getRequest()->action = 'index';
	}

	public function indexAction() {
		return;
	}
	
//	protected function run() {
//		foreach ($this->tests as $test) {
//			$this->test($test);
//		}
//	}
//	
//	protected function test($test) {
//		$d1 = strtotime($test['date1']);
//		$d2 = strtotime($test['date2']);
//		$countMonths = Billrun_Utils_Autorenew::countMonths($d1, $d2);
//		$nextRenewDate = Billrun_Utils_Autorenew::getNextRenewDate($d1, false, strtotime($test['specific_date']));
//		$testTxt = str_replace(array(PHP_EOL, '\t', '\n'), ' ', print_R($test, 1));
//		$this->log("====================================");
//		$this->log($testTxt);
//		if ($countMonths != $test['countMonths']) {
//			$this->log('count months FAILED for test. value: ' . $countMonths);
//		} else if (!$this->showOnlyFailed) {
//			$this->log('count months success');
//		}
//		
//		if ($nextRenewDate != strtotime($test['next_renew_date'])) {
//			$this->log('next renew date FAILED for test. value: ' . date('Y-m-d H:i:s', $nextRenewDate));
//		} else if (!$this->showOnlyFailed) {
//			$this->log('next renew date success');
//		}
//	}
//	
//	protected function log($log) {
//		print $log . "<br />" . PHP_EOL;
//		Billrun_Factory::log($log);
//	}

}
