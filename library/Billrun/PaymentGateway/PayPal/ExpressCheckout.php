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
class Billrun_PaymentGateway_PayPal_ExpressCheckout extends Billrun_PaymentGateway {

	protected $omnipayName = 'PayPal_Express';
	protected $conf;
	protected $billrunName = "PayPal_ExpressCheckout";
	protected $pendingCodes = "/^Pending$/";
	protected $completionCodes = "/^Completed$|^Processed$/";

	protected function __construct() {
		if (Billrun_Factory::config()->isProd()) {
			$this->EndpointUrl = "https://api-3t.paypal.com/nvp";
		} else { // test/dev environment
			$this->EndpointUrl = "https://api-3t.sandbox.paypal.com/nvp";
		}
	}

	public function updateSessionTransactionId() {
		$url_array = parse_url($this->redirectUrl);
		$str_response = array();
		parse_str($url_array['query'], $str_response);
		$this->transactionId = $str_response['token'];
	}

	protected function buildPostArray($aid, $returnUrl, $okPage, $failPage) {
		$credentials = $this->getGatewayCredentials();
		$this->conf['redirect_url'] = "https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=";
		$this->conf['return_url'] = $okPage;

		return $post_array = array(
			'USER' => $credentials['username'],
			'PWD' => $credentials['password'],
			'SIGNATURE' => $credentials['signature'],
			'METHOD' => "SetExpressCheckout",
			'VERSION' => "95",
			'AMT' => 0,
			'returnUrl' => $this->conf['return_url'],
			'cancelUrl' => $this->conf['return_url'],
			'L_BILLINGTYPE0' => "MerchantInitiatedBilling",
		);
	}

	protected function updateRedirectUrl($result) {
		if (empty($result)) {
			Billrun_Factory::log("Error: Redirecting to " . $this->returnUrlOnError . ' message: No response from ' . $this->billrunName, Zend_Log::ALERT);
			throw new Exception('No response from ' . $this->billrunName);
		}
		$resultArray = array();
		parse_str($result, $resultArray);
		if (!isset($resultArray['ACK']) || $resultArray['ACK'] != "Success") {
			Billrun_Factory::log("Error: Redirecting to " . $this->returnUrlOnError . ' message: ' . $resultArray['L_LONGMESSAGE0'], Zend_Log::ALERT);
			throw new Exception($resultArray['L_LONGMESSAGE0']);
		}

		$this->redirectUrl = $this->conf['redirect_url'] . $resultArray['TOKEN'];
	}

	protected function buildTransactionPost($txId, $additionalParams) {
		$credentials = $this->getGatewayCredentials();
		$this->conf['redirect_url'] = "https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=";

		return $post_array = array(
			'USER' => $credentials['username'],
			'PWD' => $credentials['password'],
			'SIGNATURE' => $credentials['signature'],
			'METHOD' => "CreateBillingAgreement",
			'VERSION' => "95",
			'TOKEN' => $txId,
		);
	}

	public function getTransactionIdName() {
		return "token";
	}

	protected function getResponseDetails($result) {
		if (empty($result)) {
			Billrun_Factory::log("Error: Redirecting to " . $this->returnUrlOnError . ' message: No response from ' . $this->billrunName, Zend_Log::ALERT);
			throw new Exception('No response from ' . $this->billrunName);
		}
		$resultArray = array();
		parse_str($result, $resultArray);
		if (!isset($resultArray['ACK']) || $resultArray['ACK'] != "Success") {
			Billrun_Factory::log("Error: Redirecting to " . $this->returnUrlOnError . ' message: ' . $resultArray['L_LONGMESSAGE0'], Zend_Log::ALERT);
			throw new Exception($resultArray['L_LONGMESSAGE0']);
		}
		$this->saveDetails['billing_agreement_id'] = $resultArray['BILLINGAGREEMENTID'];
	}

	protected function buildSetQuery() {
		return array(
			'active' => array(
				'name' => $this->billrunName,
				'card_token' => (string) $this->saveDetails['billing_agreement_id'],
				'transaction_exhausted' => true,
				'generate_token_time' => new MongoDate(time())
			)
		);
	}

	public function pay($gatewayDetails) {
		$paymentArray = $this->buildPaymentRequset($gatewayDetails);
		$paymentString = http_build_query($paymentArray);
		if (function_exists("curl_init")) {
			$result = Billrun_Util::sendRequest($this->EndpointUrl, $paymentString, Zend_Http_Client::POST, array('Accept-encoding' => 'deflate'), null, 0);
		}
		$status = $this->payResponse($result);
		return $status;
	}

	protected function payResponse($result) {
		$resultArray = array();
		parse_str($result, $resultArray);
		if (isset($resultArray['TRANSACTIONID'])) {
			$this->transactionId = $resultArray['TRANSACTIONID'];
		}
		return $resultArray['PAYMENTSTATUS'];
	}

	protected function buildPaymentRequset($gatewayDetails) {
		$credentials = $this->getGatewayCredentials();
		$gatewayDetails['amount'] = $this->convertAmountToSend($gatewayDetails['amount']);

		return $post_array = array(
			'USER' => $credentials['username'],
			'PWD' => $credentials['password'],
			'SIGNATURE' => $credentials['signature'],
			'METHOD' => "DoReferenceTransaction",
			'VERSION' => "95",
			'AMT' => $gatewayDetails['amount'],
			'CURRENCYCODE' => $gatewayDetails['currency'],
			'PAYMENTACTION' => "SALE",
			'REFERENCEID' => $gatewayDetails['card_token'],
		);
	}

	public function authenticateCredentials($params) {
		$authArray = array(
			'USER' => $params['username'],
			'PWD' => $params['password'],
			'SIGNATURE' => $params['signature'],
			'METHOD' => "GetPalDetails",
			'VERSION' => "95",
		);

		$authString = http_build_query($authArray);
		if (function_exists("curl_init")) {
			$result = Billrun_Util::sendRequest($this->EndpointUrl, $authString, Zend_Http_Client::POST, array('Accept-encoding' => 'deflate'), null, 0);
		}
		$resultArray = array();
		parse_str($result, $resultArray);
		if (isset($resultArray['L_LONGMESSAGE0'])) {
			$message = $resultArray['L_LONGMESSAGE0'];
		}
		if (!empty($message) && $message == "Security header is not valid") {
			return false;
		} else {
			return true;
		}
	}

	public function verifyPending($txId) {
		$response = $this->getCheckoutDetails($txId);
		return $response['PAYMENTSTATUS'];
	}

	public function hasPendingStatus() {
		return true;
	}

	/**
	 * Inquire Transaction by transaction Id to check status of a payment.
	 * 
	 * @param string $txId - String that represents the transaction.
	 * @return array - array of the response from PayPal
	 */
	protected function getCheckoutDetails($txId) {
		$credentials = $this->getGatewayCredentials();

		$requestDetails = array(
			'USER' => $credentials['username'],
			'PWD' => $credentials['password'],
			'SIGNATURE' => $credentials['signature'],
			'METHOD' => "GetTransactionDetails",
			'VERSION' => "95",
			'TRANSACTIONID' => $txId,
		);
		$requestString = http_build_query($requestDetails);
		if (function_exists("curl_init")) {
			$result = Billrun_Util::sendRequest($this->EndpointUrl, $requestString, Zend_Http_Client::POST, array('Accept-encoding' => 'deflate'), null, 0);
		}
		$resultArray = array();
		parse_str($result, $resultArray);
		return $resultArray;
	}

	public function getDefaultParameters() {
		$params = array("username", "password", "signature");
		return $this->rearrangeParametres($params);
	}
	
	protected function isRejected($status) {
		return (!$this->isCompleted($status) && !$this->isPending($status));
	}
	
	protected function convertAmountToSend($amount) {
		return $amount;
	}

	protected function isNeedAdjustingRequest() {
		return true;
	}
	
	protected function isUrlRedirect() {
		return true;
	}
	
	protected function isHtmlRedirect() {
		return false;
	}
	
	protected function needRequestForToken() {
		return true;
	}
	
	public function handleOkPageData($txId) {
		return true;
	}
	
	protected function validateStructureForCharge($structure) {
		return !empty($structure['card_token']);
	}
	
	protected function handleTokenRequestError($response, $params) {
		return false;
	}
		
	protected function buildSinglePaymentArray($params, $options) {
		throw new Exception("Single payment not supported in " . $this->billrunName);
	}

}
