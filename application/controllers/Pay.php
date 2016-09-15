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
		$gateway = Omnipay\Omnipay::create('Stripe');
		$gateway->setApiKey('abc123');

		// Example form data
		$formData = [
			'number' => '4242424242424242',
			'expiryMonth' => '6',
			'expiryYear' => '2016',
			'cvv' => '123'
		];

		// Send purchase request
		$response = $gateway->purchase(
				[
					'amount' => '10.00',
					'currency' => 'USD',
					'card' => $formData
				]
			)->send();

		// Process response
		if ($response->isSuccessful()) {

			// Payment was successful
			print_r($response);
		} elseif ($response->isRedirect()) {

			// Redirect to offsite payment gateway
			$response->redirect();
		} else {

			// Payment failed
			echo $response->getMessage();
		}
		die("AAA");
	}

}
