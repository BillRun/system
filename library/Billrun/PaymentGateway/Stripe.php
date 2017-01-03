<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */


/**
 * This class represents a payment gateway
 *
 * @since    5.2
 */
class Billrun_PaymentGateway_Stripe extends Billrun_PaymentGateway {

	protected $billrunName = "Stripe";
	protected $pendingCodes = "//";
	protected $completionCodes = "//";
	protected $billrunToken;

	protected function __construct() {
		if (Billrun_Factory::config()->isProd()) {
			$this->EndpointUrl = "";
		} else { // test/dev environment
			$this->EndpointUrl = "";
		}
	}

	public function updateSessionTransactionId() {
		$this->transactionId = $this->billrunToken;
	}

	protected function buildPostArray($aid, $returnUrl, $okPage) {
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
			'payment_gateway' => array(
				'name' => $this->billrunName,
				'customer_id' => $this->saveDetails['customer_id'],
				'token' => $this->saveDetails['token'],
				'transaction_exhausted' => true,
				'generate_token_time' => new MongoDate(time())
			)
		);
	}

	public function pay($gatewayDetails) { 
		
	}

	protected function payResponse($result) {
		
	}

	protected function buildPaymentRequset($gatewayDetails) {
		
	}

	public function authenticateCredentials($params) {
		
	}

	public function verifyPending($txId) {
		
	}

	public function hasPendingStatus() {
		
	}

	/**
	 * Inquire Transaction by transaction Id to check status of a payment.
	 * 
	 * @param string $txId - String that represents the transaction.
	 * @return array - array of the response from PayPal
	 */
	protected function getCheckoutDetails($txId) {
		
	}

	public function getDefaultParameters() {
		$params = array("secret_key", "publishable_key");
		return $this->rearrangeParametres($params);
	}

	protected function isRejected($status) {
		
	}

	protected function convertAmountToSend($amount) {
		
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

	public function isCustomerBasedCharge() {
		return false;
	}

	protected function needRequestForToken() {
		return false;
	}

	protected function buildFormForPopUp($okPage, $publishable_key) {
		return "<!DOCTYPE html>
					<html>
						<body>
							<form id='myForm' action='$okPage' method='POST'>
								<script
								  src='https://checkout.stripe.com/checkout.js' class='stripe-button'
								  data-key='$publishable_key'
								  data-amount='999'
								  data-name='Demo Site'
								  data-description='Widget'
								  data-image='https://stripe.com/img/documentation/checkout/marketplace.png'
								  data-locale='auto'>
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
		\Stripe\Stripe::setApiKey($credentials['secret_key']);
		$customer = \Stripe\Customer::create(array(
				'card' => $additionalParams['token'],
				'email' => $additionalParams['email']
				)
		);

		return $customer;
	}
	
	public function addAdditionalParameters($request) {
		$token = $request->get('stripeToken');
		$email = $request->get('stripeEmail');
		
		return array('token' => $token, 'email' => $email);
	}
	
	protected function isTransactionDetailsNeeded() {
		return false;
	}

}
