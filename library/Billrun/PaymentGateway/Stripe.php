<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

require_once(APPLICATION_PATH . '/library/stripe-php/init.php');

/**
 * This class represents a payment gateway
 *
 * @since    5.2
 */
class Billrun_PaymentGateway_Stripe extends Billrun_PaymentGateway {

	protected $billrunName = "Stripe";
	protected $pendingCodes = "/^pending$/";
	protected $completionCodes = "/^succeeded$/";
	protected $rejectionCodes = "/^failed$/";
	protected $billrunToken;

	public function updateSessionTransactionId() {
		$this->transactionId = $this->billrunToken;
	}

	protected function buildPostArray($aid, $returnUrl, $okPage, $failPage) {
		return false;
	}

	protected function updateRedirectUrl($result) {
		$credentials = $this->getGatewayCredentials();
		$this->htmlForm = $this->buildFormForPopUp($result, $credentials['publishable_key']);
	}

	protected function buildTransactionPost($txId, $additionalParams) {
		$customer = $this->createCustomer($additionalParams);
		$customer_id = $customer->id;
		$this->saveDetails['customer_id'] = $customer_id;
		$this->saveDetails['token'] = $additionalParams['token'];
		$this->saveDetails['email'] = $additionalParams['email'];

		return array();
	}

	public function getTransactionIdName() {
		return "tok";
	}

	protected function getResponseDetails($result) {
		return true;
	}

	protected function buildSetQuery() {
		return array(
			'active' => array(
				'name' => $this->billrunName,
				'customer_id' => $this->saveDetails['customer_id'],
				'stripe_email' => $this->saveDetails['email'],
				'token' => $this->saveDetails['token'],
				'transaction_exhausted' => true,
				'generate_token_time' => new Mongodloid_Date(time())
			)
		);
	}

	public function pay($gatewayDetails, $addonData) {
		$credentials = $this->getGatewayCredentials();
		$this->setApiKey($credentials['secret_key']);
		$gatewayDetails['amount'] = $this->convertAmountToSend($gatewayDetails['amount']);
		$result = \Stripe\Charge::create(array(
				"amount" => $gatewayDetails['amount'],
				"currency" => $gatewayDetails['currency'],
				"customer" => $gatewayDetails['customer_id'],
		));
		$status = $this->payResponse($result);

		return array ('status' => $status, 'additional_params' => [],);
	}

	protected function payResponse($result) {
		if (isset($result['id'])) {
			$this->transactionId = $result['id'];
		}

		return $result['status'];
	}

	public function authenticateCredentials($params) {
		$this->validatingSecretKey($params['secret_key']);
		$this->validatingPublishableKey($params['publishable_key']);
		
		return true;
	}

	public function verifyPending($txId) {

	}

	public function hasPendingStatus() {
		return false;
	}

	public function getDefaultParameters() {
		$params = array("secret_key", "publishable_key");
		return $this->rearrangeParametres($params);
	}

	protected function convertAmountToSend($amount) {
		$amount = round($amount, 2);
		return $amount * 100;
	}

	protected function isNeedAdjustingRequest() {
		return false;
	}

	protected function isUrlRedirect() {
		return false;
	}

	protected function isHtmlRedirect() {
		return true;
	}

	protected function needRequestForToken() {
		return false;
	}

	protected function buildFormForPopUp($okPage, $publishable_key) {
		return "<!DOCTYPE html>
					<html>
						<body onload='wait()'>
							<style>
								body *:not(.myForm) {
									 display: none;
								}
							</style>
							<form id='myForm' action='$okPage' method='POST'>
								<script
								  src='https://checkout.stripe.com/checkout.js' class='stripe-button'
								  data-key='$publishable_key'
								  data-name='Billrun'
								  data-description='Cloud'
								  data-image='https://stripe.com/img/documentation/checkout/marketplace.png'
								  data-locale='auto'>
								</script>
								<script type='text/javascript'>
									function wait() {
										setTimeout(click, 1000);
									}				
									function click() {
										document.getElementsByTagName('button')[0].click();
										document.getElementsById('myForm').submit();
									}

								</script>
							 </form>
							</body>
					</html>";
	}

	public function adjustOkPage($okPage) {
		$this->billrunToken = md5(microtime() . rand(1, 700000));
		$updatedOkPage = $okPage . '&amp;tok=' . $this->billrunToken;
		return $updatedOkPage;
	}

	protected function createCustomer($additionalParams) {
		$credentials = $this->getGatewayCredentials();
		$this->setApiKey($credentials['secret_key']);
		try {
			$customer = \Stripe\Customer::create(array(
					'card' => $additionalParams['token'],
					'email' => $additionalParams['email']
					)
			);
		} catch(Exception $e) {
			throw new Exception('Error creating customer!');
		}
		
		return $customer;
	}

	public function addAdditionalParameters() {
		$token = $request->get('stripeToken');
		$email = $request->get('stripeEmail');

		return array('token' => $token, 'email' => $email);
	}

	protected function isTransactionDetailsNeeded() {
		return false;
	}

	protected function setApiKey($secretKey) {
		\Stripe\Stripe::setApiKey($secretKey);
	}
	
	protected function validatingPublishableKey($publishableKey) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://api.stripe.com/v1/tokens");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "card[number]=''&card[exp_month]=''&card[exp_year]=''&card[cvc]=''");
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_USERPWD, $publishableKey . ":");
		$response = json_decode(curl_exec($ch), true);
		curl_close($ch);

		$errorMessage = isset($response["error"]["message"]) ? $response["error"]["message"] : '';
		if (!empty($errorMessage)) {
			if (substr($errorMessage, 0, 24) == 'Invalid API Key provided') {
				throw new Exception($errorMessage);
			}
		}
	}
	
	protected function validatingSecretKey($secretKey) {
		$this->setApiKey($secretKey);
		\Stripe\Balance::retrieve(); // calling function from Stripe API to check if connection succeeded
	}

	public function handleOkPageData($txId) {
		return true;
	}
	
	protected function validateStructureForCharge($structure) {
		return !empty($structure['customer_id']) && !empty($structure['token']);
	}
	
	protected function handleTokenRequestError($response, $params) {
		return false;
	}
	
	protected function buildSinglePaymentArray($params, $options) {
		throw new Exception("Single payment not supported in " . $this->billrunName);
	}

	public function createRecurringBillingProfile($aid, $gatewayDetails, $params = []) {
		return false;
	}

	public function getSecretFields() {
		return array('secret_key');
	}
}
