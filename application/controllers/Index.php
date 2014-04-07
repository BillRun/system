<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing index controller class
 *
 * @package  Controller
 * @since    0.5
 */
class IndexController extends Yaf_Controller_Abstract {

	public function indexAction() {
		
		foreach (array("031234567", "31234567", "97231234567", "0501234567", "501234567", "972541234567", "741234567", "74994648317", "1-234-235-5678", "12342355678", "1800123456", "+44 (0) 1522 814010", "100") as $a) {
			print $a . "<br />" . PHP_EOL;
			$r = Billrun_Util::msisdn($a);
			print "Return: " . $r . "<br />" . PHP_EOL;
			print "<br />" . PHP_EOL;
		}
		die;
		$this->redirect('admin');
		$this->getView()->title = "BillRun | The best open source billing system";
		$this->getView()->content = "Open Source Last Forever!";
		$this->getView()->favicon = Billrun_Factory::config()->getConfigValue('favicon');
	}

}
