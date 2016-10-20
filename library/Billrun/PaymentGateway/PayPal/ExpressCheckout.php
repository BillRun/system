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
	protected $EndpointUrl;
	protected $billrunName = "PayPal_ExpressCheckout";

	protected function __construct() {
		if (Billrun_Factory::config()->isProd()) {
			// TODO: define 'live' url for payment gateway.
		} else { // testing environment
			$this->EndpointUrl = "https://api-3t.sandbox.paypal.com/nvp";
		}
	}

	public function updateSessionTransactionId() {
		$url_array = parse_url($this->redirectUrl);
		$str_response = array();
		parse_str($url_array['query'], $str_response);
		$this->transactionId = $str_response['token'];
	}

	protected function buildPostArray($aid, $returnUrl, $okPage) {
		$this->conf['user'] = "shani.dalal_api1.billrun.com";
		$this->conf['password'] = "RRM2W92HC9VTPV3Y";
		$this->conf['signature'] = "AiPC9BjkCyDFQXbSkoZcgqH3hpacA3CKMEmo7jRUKaB3pfQ8x5mChgoR";
		$this->conf['return_url'] = $okPage;
		$this->conf['redirect_url'] = "https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=";

		return $post_array = array(
			'USER' => $this->conf['user'],
			'PWD' => $this->conf['password'],
			'SIGNATURE' => $this->conf['signature'],
			'METHOD' => "SetExpressCheckout",
			'VERSION' => "95",
			'AMT' => 0,
			'returnUrl' => $this->conf['return_url'],
			'cancelUrl' => $this->conf['return_url'],
			'L_BILLINGTYPE0' => "MerchantInitiatedBilling",
		);
	}

	protected function updateRedirectUrl($result) {
		parse_str($result, $resultArray);
		if (!isset($resultArray['ACK']) || $resultArray['ACK'] != "Success") {
			throw new Exception($resultArray['L_LONGMESSAGE0']);
		}

		$this->redirectUrl = $this->conf['redirect_url'] . $resultArray['TOKEN'];
	}

	protected function buildTransactionPost($txId) {
		$this->conf['user'] = "shani.dalal_api1.billrun.com";
		$this->conf['password'] = "RRM2W92HC9VTPV3Y";
		$this->conf['signature'] = "AiPC9BjkCyDFQXbSkoZcgqH3hpacA3CKMEmo7jRUKaB3pfQ8x5mChgoR";
		//$this->conf['return_url'] = $returnUrl;	
		$this->conf['redirect_url'] = "https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=";

		return $post_array = array(
			'USER' => $this->conf['user'],
			'PWD' => $this->conf['password'],
			'SIGNATURE' => $this->conf['signature'],
			'METHOD' => "CreateBillingAgreement",
			'VERSION' => "95",
			'TOKEN' => $txId,
		);
	}

	public function getTransactionIdName() {
		return "token";
	}

	protected function getResponseDetails($result) {
		$resultArray = array();
		parse_str($result, $resultArray);
		if (!isset($resultArray['ACK']) || $resultArray['ACK'] != "Success") {
			throw new Exception($resultArray['L_LONGMESSAGE0']);
		}
		$this->saveDetails['billing_agreement_id'] = $resultArray['BILLINGAGREEMENTID'];
	}

	protected function buildSetQuery() {
		return array(
			'payment_gateway' => array(
				'name' => $this->billrunName,
				'card_token' => (string) $this->saveDetails['billing_agreement_id'],
				'transaction_exhausted' => true
			)
		);
	}

	protected function setConnectionParameters($params) {
		if ((!isset($params["username"])) || (!isset($params["password"])) || (!isset($params["signature"]))) {
			throw new Exception("Missing necessary credentials");
		}

		return array(
			'username' => $params["username"],
			'password' => $params["password"],
			'signature' => $params["signature"]
		);
	}

	protected function pay($gatewayDetails) {
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
		if (!isset($resultArray['ACK']) || $resultArray['ACK'] != "Success") {
			throw new Exception($resultArray['L_LONGMESSAGE0']);
		}
		return $resultArray['PAYMENTSTATUS'];
	}

	protected function buildPaymentRequset($gatewayDetails) {
		$this->conf['user'] = "shani.dalal_api1.billrun.com";
		$this->conf['password'] = "RRM2W92HC9VTPV3Y";
		$this->conf['signature'] = "AiPC9BjkCyDFQXbSkoZcgqH3hpacA3CKMEmo7jRUKaB3pfQ8x5mChgoR";

		return $post_array = array(
			'USER' => $this->conf['user'],
			'PWD' => $this->conf['password'],
			'SIGNATURE' => $this->conf['signature'],
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
		$message = $resultArray['L_LONGMESSAGE0'];
		if ($message == "Security header is not valid") {
			return false;
		} else {
			return true;
		}
	}

	protected function isCompleted($status) {
		if ($status == "Completed" || $status == "Processed") {
			return true;
		}
		return false;
	}

	protected function isPending($status) {
		if ($status == "Pending") {
			return true;
		}
		return false;
	}

	protected function isRejected($status) {
		if ($status == "Expired" || $status == "Failed") {
			return true;
		}
		return false;
	}
	
	protected function verifyPending(){
		// TODO: need to find out how to verify pending for each payment gateway.
	}

}
