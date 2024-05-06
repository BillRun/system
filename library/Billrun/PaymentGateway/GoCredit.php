<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class represents the Go Credit payment gateway 
 *
 * @since    5.16
 */
class Billrun_PaymentGateway_GoCredit extends Billrun_PaymentGateway {

	protected $conf;
	protected $billrunName = "GoCredit";
	protected $pendingCodes = "/$^/";
	protected $completionCodes = "/^Success$/";
	protected $account;

	protected function __construct($instanceName =  null) {
		parent::__construct($instanceName);
		$this->EndpointUrl = $this->getGatewayCredentials()['endpoint_url'];
	}
        
	public function updateSessionTransactionId($result) {
		if (function_exists("simplexml_load_string")) {
			$body = $this->convertSoap($result);
		
			if ($body->CreatePaymentRequestResponse->CreatePaymentRequestResult) {
				$this->transactionId = (string)$body->CreatePaymentRequestResponse->sPrivatePaymentRequestID;
				$this->setRequestParams();
			} else {
				Billrun_Factory::log("Error: " . 'Error Code: ' . $body->CreatePaymentRequestResponse->Response->ResponseCode .
					'Message: ' . $body->CreatePaymentRequestResponse->Response->Message, Zend_Log::ALERT);
				throw new Exception('Can\'t Create Transaction');
			}
		} else {
			die("simplexml_load_string function is not support, upgrade PHP version!");
		}
	}

	protected function buildPostArray($aid, $returnUrl, $okPage, $failPage) {
		$credentials = $this->getGatewayCredentials();
		$postParams['LoginParams'] = [ "Login" => $credentials['user'], "Password" => $credentials['password']];
		$postParams['RequestParams'] = [];
		$postParams['RequestParams']['PaymentPageSecretKey'] = $credentials['payment_page_secret_key'];
		$postParams['RequestParams']['TransactionSum'] = (int) Billrun_Factory::config()->getConfigValue('GC.conf.amount', 1);
		$postParams['RequestParams']['TransactionMode'] = 'RequestApprovalJ5';
		$postParams['RequestParams']['Currency'] = 'ILS';
		$postParams['RequestParams']['URLSuccess'] = $okPage;
		$postParams['RequestParams']['URLBack'] = $returnUrl;
		$postParams['RequestParams']['RefCustomerCode'] = $aid;
		return $this->buildSoapRequest('CreatePaymentRequest', $postParams);
	}

	protected function buildSoapRequest($request, $params) {
		$this->requestHeaders = array(
			'Content-Type: text/xml',
			"SOAPAction: " . "http://tempuri.org/$request"
		);
		$soapRequest = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><' . $request . ' xmlns="http://tempuri.org/">' . $this->buildSoapBody($params) . '</'. $request . '></soap:Body></soap:Envelope>';
		return $soapRequest;
	}

	protected function buildSoapBody($data) {
		if (is_array($data)) {
			$body = '';
			foreach ($data as $tag => $value) {
				$body = $body . '<' . $tag . '>' . $this->buildSoapBody($value) . '</' . $tag . '>';
			}
			return $body;
		} else {
			return $data;
		}
	}

	protected function updateRedirectUrl($result) {
		if (function_exists("simplexml_load_string")) {
			$body = $this->convertSoap($result);
		
			if ($body->CreatePaymentRequestResponse->CreatePaymentRequestResult) {
				$this->redirectUrl = (string)$body->CreatePaymentRequestResponse->sRedirectURL;
				$this->setRequestParams();
			} else {
				Billrun_Factory::log("Error: " . 'Error Code: ' . $body->CreatePaymentRequestResponse->Response->ResponseCode .
					'Message: ' . $body->CreatePaymentRequestResponse->Response->Message, Zend_Log::ALERT);
				throw new Exception('Can\'t Create Transaction');
			}
		} else {
			die("simplexml_load_string function is not support, upgrade PHP version!");
		}
	}
	
	protected function setRequestParams($params = []) {
		$this->requestParams = [
			'url' => $this->redirectUrl,
			'post_parameters' => [
			],
			'response_parameters' => [
				'sPrivatePaymentRequestID',
			],
		];
	}

	protected function buildTransactionPost($txId, $additionalParams) {
		$credentials = $this->getGatewayCredentials();
		$postParams['LoginParams'] = [ "Login" => $credentials['user'], "Password" => $credentials['password']];
		$postParams['sPrivatePaymentRequestID'] = $txId;
		return $this->buildSoapRequest('CommitPaymentRequest', $postParams);
	}

	public function getTransactionIdName() {
		return "PrivateRequestID";
	}

	protected function getResponseDetails($result) {
		if (function_exists("simplexml_load_string")) {
			$body = $this->convertSoap($result);
			if (!isset($body->CommitPaymentRequestResponse->Response->ResponseCode) ||
				!preg_match($this->completionCodes, (string)$body->CommitPaymentRequestResponse->Response->ResponseCode)) {
				return false;
			}

			$this->saveDetails['card_token'] = (string)$body->CommitPaymentRequestResponse->PaymentRequestResponse->Token;
			$this->saveDetails['card_expiration'] = (string)$body->CommitPaymentRequestResponse->PaymentRequestResponse->CreditCardExpiryDateMMYY;
			$this->saveDetails['personal_id'] = (string)$body->CommitPaymentRequestResponse->PaymentRequestResponse->CreditCardHolderPersonalID;
			$this->saveDetails['shva_approval_number'] = (string)$body->CommitPaymentRequestResponse->PaymentRequestResponse->ShvaApprovalNumber;
			$this->saveDetails['credit_company'] = (string) (string)$body->CommitPaymentRequestResponse->PaymentRequestResponse->ProcessedBy;
			$this->saveDetails['card_brand'] = (string)$body->CommitPaymentRequestResponse->PaymentRequestResponse->Brand;
			$this->saveDetails['transaction_id'] = (string)$body->CommitPaymentRequestResponse->PaymentRequestResponse->TransactionID;
			$retParams['card_token'] = $this->saveDetails['card_token'];		
			$retParams['personal_id'] = $this->saveDetails['personal_id'];
			$retParams['auth_number'] = $this->saveDetails['auth_number'];
			$retParams['credit_company'] = $this->saveDetails['credit_company'];
			$retParams['card_brand'] = $this->saveDetails['card_brand'];
			$retParams['four_digits'] = $this->saveDetails['four_digits'] = (string)$body->CommitPaymentRequestResponse->PaymentRequestResponse->CreditCard4Digits;
			$retParams['expiration_date'] = (string)$body->CommitPaymentRequestResponse->PaymentRequestResponse->CreditCardExpiryDateMMYY;
			$retParams['terminal_number'] = (string)$body->CommitPaymentRequestResponse->PaymentRequestResponse->TerminalNumber;
			return $retParams;
		} else {
			die("simplexml_load_string function is not support, upgrade PHP version!");
		}
	}

	protected function buildSetQuery() {
		return array(
			'active' => array(
				'name' => $this->billrunName,
				'instance_name' => $this->instanceName,
				'card_token' => (string) $this->saveDetails['card_token'],
				'card_expiration' => (string) $this->saveDetails['card_expiration'],
				'personal_id' => (string) $this->saveDetails['personal_id'],
				'transaction_exhausted' => true,
				'generate_token_time' => new Mongodloid_Date(time()),
				'shva_approval_number' => (string) $this->saveDetails['shva_approval_number'],
				'four_digits' => (string) $this->saveDetails['four_digits'],
				'card_brand' => (string) $this->saveDetails['card_brand'],
				'credit_company' => (string) $this->saveDetails['credit_company']
			)
		);
	}

	public function getDefaultParameters() {
		$params = array("user", "password", "terminal", "endpoint_url", "payment_page_secret_key");
		return $this->rearrangeParametres($params);
	}
	
	public function authenticateCredentials($params) {
		$params['txId'] = 1;
		$authString = $this->buildTestConnectionQuery($params);
		if (function_exists("curl_init")) {
			Billrun_Factory::log("Sending to Go Credit (authenticateCredentials): " . $params['endpoint_url'] . ' ' . $authString, Zend_Log::DEBUG);
			$result = Billrun_Util::sendRequest($params['endpoint_url'], $authString, Zend_Http_Client::POST, $this->requestHeaders, null, 0);
		}
		$body = $this->convertSoap($result);
		$codeResult = (bool) $body->TestConnectionResponse->TestConnectionResult;
		Billrun_Factory::log("Go Credit response (authenticateCredentials):" . print_r($body, 1), Zend_Log::DEBUG);
		if (!$codeResult || empty($result)) {
			Billrun_Factory::log("Go Credit error (authenticateCredentials):" . print_r($body, 1), Zend_Log::ERR);
			return false;
		} else {
			return true;
		}
	}

	public function pay($gatewayDetails, $addonData) {
		return $this->sendPaymentRequest($gatewayDetails, $addonData);
	}

	protected function buildPaymentRequset($gatewayDetails, $addonData) {
		$credentials = $this->getGatewayCredentials();
		$postParams['LoginParams'] = [ "Login" => $credentials['user'], "Password" => $credentials['password']];
		$postParams['TransactionParams'] = [];
		$postParams['TransactionParams']['CreditCard'] = $gatewayDetails['card_token'];
		$postParams['TransactionParams']['CreditCardExpiryDateMMYY'] = $gatewayDetails['card_expiration'];
		$postParams['TransactionParams']['CreditCardHolderPersonalID'] = $gatewayDetails['personal_id'];
		$postParams['TransactionParams']['Currency'] = 'ILS';
		$postParams['TransactionParams']['Sum'] = $gatewayDetails['amount'];
		$postParams['TransactionParams']['CreditType'] = 'Regular';
		$postParams['TransactionParams']['TerminalNumber'] = $credentials['terminal'];
		$postParams['TransactionParams']['ManualApprovalNumber'] = $gatewayDetails['shva_approval_number'];
		$postParams['TransactionParams']['Mode'] = 'RegularJ4';
		$postParams['TransactionParams']['RefCustomerCode'] = $addonData['aid'];
		return $this->buildSoapRequest('ExecuteTransaction', $postParams);
	}

	public function verifyPending($txId) {
		
	}

	public function hasPendingStatus() {
		return false;
	}
	
	protected function buildTestConnectionQuery($params) {
		$postParams = [];
		$postParams['LoginParams'] = [ "Login" => $params['user'], "Password" => $params['password']];
		return $this->buildSoapRequest('TestConnection', $postParams);
	}
	
	protected function isRejected($status) {
		return (!$this->isCompleted($status) && !$this->isPending($status));
	}
	
	protected function isNeedAdjustingRequest(){
		return false;
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
		return !empty($structure['card_token']) && !empty($structure['card_expiration']);
	}
	
	protected function handleTokenRequestError($response, $params) {
		return false;
	}

	protected function credit($gatewayDetails, $addonData) {
		return $this->sendPaymentRequest($gatewayDetails, $addonData);
	}
	
	protected function sendPaymentRequest($gatewayDetails, $addonData) {
		$paymentString = $this->buildPaymentRequset($gatewayDetails, $addonData);
		$additionalParams = array();
		$codeResult = '';
		if (function_exists("curl_init")) {
			Billrun_Factory::log("Go Credit payment request: " . print_R($paymentString, 1), Zend_Log::DEBUG);
			$result = Billrun_Util::sendRequest($this->EndpointUrl, $paymentString, Zend_Http_Client::POST, $this->requestHeaders, null, 0);
			Billrun_Factory::log("Go Credit payment response: " . print_R($result, 1), Zend_Log::DEBUG);
		}
		$body = $this->convertSoap($result);
		if ($body !== false) {
			$codeResult = (string) $body->ExecuteTransactionResponse->Response->ResponseCode;
			$this->transactionId = (string) $body->ExecuteTransactionResponse->TransactionResponse->TransactionID;
			$additionalParams['payment_identifier'] = $this->transactionId;
			$additionalParams['card_brand'] = (string) $body->ExecuteTransactionResponse->TransactionResponse->Brand;
			$additionalParams['credit_company'] = (string) $body->ExecuteTransactionResponse->TransactionResponse->ProcessedBy;
		}	
		return array('status' => $codeResult, 'additional_params' => $additionalParams);
	}

	public function createRecurringBillingProfile($aid, $gatewayDetails, $params = []) {
		return false;
	}
	
	public function addAdditionalParameters($request) {
		return array();
	}
	
	public function getSecretFields() {
		return array('password');
	}
	
	protected function convertSoap($xml) {
		$xmlObj = simplexml_load_string(preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $xml));
		$body = $xmlObj->xpath('//soapBody')[0];
		return $body;
	}

	protected function convertAmountToSend($amount) {
		return $amount;
	}

	/**
	 * @inheritDoc
	 */
	protected function buildSinglePaymentArray($params, $options) {
		// not applicable for GoCredit client
		return [];
	}

	/**
	 * Returns relevant transaction data for ok page response.
	 */
	public function getTransactionDetails($details) {
		return array(
			'credit_card' => $details['creditCard'], 
			'expiration_date' => $details['expirationDate'],
			'card_token' => (string) $this->saveDetails['card_token'],
			'personal_id' => (string) $this->saveDetails['personal_id'],
			'four_digits' => (string) $this->saveDetails['four_digits'],
			'auth_number' => (string) $this->saveDetails['shva_approval_number'],
		);
	}
}
