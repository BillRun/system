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

	public function init() {
		if (Billrun_Factory::config()->isProd()) {
			die("Cannot run on production environment");
		}
		Billrun_Test::getInstance($this->getRequest()->action);
		$this->getRequest()->action = 'index';
	}
	
//	public function indexAction() {
//		
//	}

}
