<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing index controller class
 *
 * @package  Controller
 * @since    1.0
 */
class IndexController extends Yaf_Controller_Abstract {

	public function indexAction() {
		$this->getView()->title = "BillRun | The best open source billing system";
		$this->getView()->content = "Open Source Last Forever!";
	}

}
