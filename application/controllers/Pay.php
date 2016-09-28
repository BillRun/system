<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing api controller class
 *
 * @package  Controller
 * @since    5.2
 */
class PayController extends Yaf_Controller_Abstract {

	public function indexAction() {
		// temporary block on production
		if (Billrun_Factory::config()->isProd()) {
			die();
		}
		$path = APPLICATION_PATH . '/library/vendor/';
		require_once $path . 'autoload.php';
		$gateway = Omnipay\Omnipay::create('PayPal_Express');
		print "<pre>" . print_R($gateway, 1) . "</pre>";
		die();
	}

}
