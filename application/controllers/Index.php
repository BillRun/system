<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
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
		$this->redirect('index.html');
		die;
		$this->getView()->title = "BillRun | The best open source billing system";
		$this->getView()->content = "Open Source Last Forever!";
		$this->getView()->favicon = 'favicon.ico';
	}

}
