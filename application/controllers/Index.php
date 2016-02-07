<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
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
		$this->redirect('admin');
		die;
		$this->getView()->title = "BillRun | The best open source billing system";
		$this->getView()->content = "Open Source Last Forever!";
		$this->getView()->favicon = Billrun_Factory::config()->getConfigValue('favicon');
	}

}
