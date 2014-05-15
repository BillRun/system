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
		echo "<pre>";
		echo Billrun_Util::msisdn('000972576677003') . PHP_EOL;
		echo Billrun_Util::msisdn('972542080308') . PHP_EOL;
		echo Billrun_Util::msisdn('0577304005') . PHP_EOL;
		echo Billrun_Util::msisdn('000033622359887') . PHP_EOL;
		die;
		$this->redirect('admin');
		$this->getView()->title = "BillRun | The best open source billing system";
		$this->getView()->content = "Open Source Last Forever!";
		$this->getView()->favicon = Billrun_Factory::config()->getConfigValue('favicon');
	}

}
