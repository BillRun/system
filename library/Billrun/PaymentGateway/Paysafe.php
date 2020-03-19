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
class Billrun_PaymentGateway_Paysafe extends Billrun_PaymentGateway {

	protected $conf;
	protected $billrunName = "Paysafe";
	protected $subscribers;
	protected $pendingCodes = "/^PENDING$/";
	protected $completionCodes = "/^COMPLETED$/";
	protected $account;
        protected $customerId;

        protected function __construct() {
		parent::__construct();
                if (Billrun_Factory::config()->isProd()) {
			$this->EndpointUrl = "https://api.paysafe.com";
                         $this->redirectHostUrl = "https://api.netbanx.com/hosted/v1/orders";
		} else { // test/dev environment
			$this->EndpointUrl = "https://api.test.paysafe.com";
                        $this->redirectHostUrl = "https://api.test.netbanx.com/hosted/v1/orders";
		}
		$this->subscribers = Billrun_Factory::db()->subscribersCollection();
		$this->account = Billrun_Factory::account();
	}
        
	public function updateSessionTransactionId() {
            $this->transactionId = $this->customerId;
	}
        

        
	protected function updateRedirectUrl($result) {
	}
              
        
        
	protected function buildTransactionPost($txId, $additionalParams) {
		$this->saveDetails['card_token'] = $txId;
	}
        
	public function getTransactionIdName() {
		return "profile.paymentToken";
	}
        
    	protected function getResponseDetails($result) {
		true;
	}

	protected function buildSetQuery() {
		return array(
			'active' => array(
				'name' => $this->billrunName,
				'card_token' => (string) $this->saveDetails['card_token'],
				'transaction_exhausted' => true,
                                'generate_token_time' => new MongoDate(time())
			)
		);
	}
        
	public function getDefaultParameters() {
		$params = array("Username", "Password", "Account", "Version");
		return $this->rearrangeParametres($params);
	}

	public function authenticateCredentials($params) {
		$authArray = array(
			'Username' => $params['Username'],
			'Password' => $params['Password'],
                        'Account' => $params['Account'],
                        'Version' => $params['Version'],
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
        //
	public function pay($gatewayDetails, $addonData) {
            $paymentArray = $this->buildPaymentRequset($gatewayDetails, 'Debit', $addonData);
            return $this->sendPaymentRequest($paymentArray, $addonData);
	}
        
        protected function buildPaymentRequset($gatewayDetails, $transactionType, $addonData) {	
		return array(
                    "merchantRefNum" => $addonData['aid'],
                    "amount" => $this->convertAmountToSend($gatewayDetails['amount']),
                    "settleWithAuth" => true,
                    "card" => array('paymentToken' => $gatewayDetails['paymentToken'])
                );
	}

	public function verifyPending($txId) {
		
	}

	public function hasPendingStatus() {
		return false;
	}
	
	protected function isRejected($status) {
		return (!$this->isCompleted($status) && !$this->isPending($status));
	}
	
	protected function convertAmountToSend($amount) {
		$amount = round($amount, 2);
		return $amount * 100;
	}
	
	protected function convertReceivedAmount($amount) {
		return $amount / 100;
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
		return !empty($structure['card_token']);
	}
	
	protected function handleTokenRequestError($response, $params) {
		return false;
	}

	protected function credit($gatewayDetails, $addonData) {
		$paymentArray = $this->buildPaymentRequset($gatewayDetails, 'Credit', $addonData);
		return $this->sendPaymentRequest($paymentArray, $addonData);
	}
	
	protected function sendPaymentRequest($paymentArray, $addonData) {
                $credentials = $this->getGatewayCredentials();
                $url = $this->EndpointUrl."/cardpayments/" .$credentials['Version'] . "/accounts/" . $credentials['Account'] . "/auths";
                $userpwd = $credentials['Username'].":".$credentials['Password'];
                $encodedAuth = base64_encode($userpwd);
                $paymentData = json_encode($paymentArray);
		if (function_exists("curl_init")) {
			$result = Billrun_Util::sendRequest($url, $paymentData, Zend_Http_Client::POST, array('Content-Type: application/json', 'Authorization: Basic '.$encodedAuth), null, 0);
		}
		if (strpos(strtoupper($result), 'HEB')) {
			$result = iconv("utf-8", "iso-8859-8", $result);
		}
		$status = $this->payResponse($result, $addonData);
		return $status;
	}
        
        protected function payResponse($result, $addonData = []) {
                $arrResponse = json_decode($result, true);
		$resultCode = isset($arrResponse['status']) ? $arrResponse['status'] : "FAILED";
		$additionalParams = [];
		if ($resultCode != 'COMPLETED') {
                    $errorMessage = $arrResponse['error']['message'];
                    $status = $arrResponse['error']['code'];
		} else {
			$status = $resultCode;
		}
		return [
			'status' => $status,
			'additional_params' => $additionalParams,
		];
	}

	protected function buildSinglePaymentArray($params, $options) {
		throw new Exception("Single payment not supported in " . $this->billrunName);
	}
        
        public function createRecurringBillingProfile($aid, $gatewayDetails, $params = []) {
		return false;
	}
        
        protected function buildPostArray($aid, $returnUrl, $okPage, $failPage) {
		$customerProfileId = $this->checkIfCustomerExists($aid);
		if (empty($customerProfileId)) {
			$customerProfileId = $this->createCustomer($aid, $okPage, $failPage);
		}
		$this->customerId = $customerProfileId;
                return false;
	}
        
        protected function createCustomer($aid, $okPage, $failPage) {
		$credentials = $this->getGatewayCredentials();
                $userpwd = $credentials['Username'].":".$credentials['Password'];
                $encodedAuth = base64_encode($userpwd);
                $url = $this->redirectHostUrl;
                $data = array(
		"merchantRefNum"=> $merchantCustomerId,
		  "totalAmount"=> 0,
		"currencyCode"=> "USD",
		 "profile" => [
			"merchantCustomerId" => $merchantCustomerId,
		   ],
		"redirect" => [
		      [
			 "rel"=>"on_success",
			 "uri"=>"https://api.test.netbanx.com/echo?payment=success",
			"returnKeys" => [
				"id",
				"profile.id",
				"profile.paymentToken"
			]
		      ]
		   ],
                );
                $customerRequest = json_encode($data);
		if (function_exists("curl_init")) {
			Billrun_Factory::log("Request for creating customer: " . $customerRequest, Zend_Log::DEBUG);
			$result = Billrun_Util::sendRequest($url, $customerRequest, Zend_Http_Client::POST, array('Content-Type: application/json', 'Authorization: Basic '.$encodedAuth), null, 0);
			Billrun_Factory::log("Response for Paysafe for creating customer request: " . $result, Zend_Log::DEBUG);
		}
                $arrResponse = json_decode($result, true);
                if (isset($resultCode['error'])) {
                    $errorMessage = $arrResponse['error']['message'];
                    $errorCode = $arrResponse['error']['code'];
                    Billrun_Factory::log("Error: Redirecting to " . $this->returnUrlOnError . ' message: ' . $errorMessage, Zend_Log::ALERT);
                    throw new Exception($errorMessage); 
		}else{
                    $customerId = $arrResponse['profile']['id'];
                    $this->redirectUrl = $arrResponse['link'][0]['uri'];
                }
		return $customerId;
	}
        
        protected function checkIfCustomerExists ($aid) {
		$customerProfileId = '';
		$accountQuery = Billrun_Utils_Mongo::getDateBoundQuery();
		$accountQuery['type'] = 'account';
		$accountQuery['aid'] = $aid;
		$subscribers = Billrun_Factory::db()->subscribersCollection();
		$account = $subscribers->query($accountQuery)->cursor()->current();
		$formerGateways = $account['payment_gateway.former'];
		if (is_null($formerGateways)) {
			return $customerProfileId;
		}
		foreach ($formerGateways as $gateway) {
			if ($gateway['name'] == 'Paysafe') {
				$customerProfileId = $gateway['params']['customer_profile_id'];
			}
		}
		
		return $customerProfileId;
	}
}