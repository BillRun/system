<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing index controller class
 *
 * @package  Controller
 * @since    0.5
 */
class IndexController extends Yaf_Controller_Abstract {

	public function indexAction() {
		$a = range("1", "1000");
		$c = array();
		foreach ($a as $b) {
			$c["a"] = $b;
		}
		$t = microtime(true);
		for ($i=0; $i<=1; $i++) {
			serialize(unserialize($b));
		}
		
		die((microtime(true) - $t) . "<br />END");
		$this->redirect('admin');
		$this->getView()->title = "BillRun | The best open source billing system";
		$this->getView()->content = "Open Source Last Forever!";
		$this->getView()->favicon = Billrun_Factory::config()->getConfigValue('favicon');
	}

}
