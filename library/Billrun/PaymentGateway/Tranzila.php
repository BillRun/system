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
class Billrun_PaymentGateway_Tranzila extends Billrun_PaymentGateway {

	protected $billrunName = "Tranzila";
	protected $pendingCodes = "/$^/";
	protected $completionCodes = "/^000$/";
	protected $okPage;
	protected $failPage;
	protected $aid;
	protected $amount;
	protected $operation;
	protected $installments;
	protected $tokenize = false;
	protected $endpointapi;


	const ENDPOINT_API = 'https://api.tranzila.com/';
	const ENDPOINT_REDIRECT = 'https://direct.tranzila.com/';

	const HANDSHAKE_PATH = 'v1/handshake/create/';
	const PAY_PATH = 'v1/transaction/credit_card/create';
	const TRANSACTIONS_PATH = 'v1/transactions';

	const OPERATION_TOKEN = 'VK';
	const OPERATION_PAY = 'AK';

	
	protected function __construct($instanceName =  null) {
		parent::__construct($instanceName);
		$this->EndpointUrl = $this->getEndpointApi() . self::HANDSHAKE_PATH;
		
	}

	protected function buildPostArray($aid, $returnUrl, $okPage, $failPage) {
		$this->aid = $aid;
		$this->okPage = $okPage;
		$this->failPage = $failPage;
		$creds = $this->getGatewayCredentials();
		$this->amount = !empty($creds['j5_amount']) ? $creds['j5_amount'] : '1';
		$this->operation = self::OPERATION_TOKEN;
		$ret = [
			'supplier' => $creds['terminal_name'],
			'sum' => $this->amount,
			'TranzilaPW' => $creds['handshake_password'],
		];
		return $ret;
		
	}
		
	protected function buildSetQuery() {
		return [
			'active' => array(
				'name' => $this->billrunName,
				'instance_name' => $this->instanceName,
				'card_token' => (string) $this->saveDetails['card_token'],
				'card_expiration' => (string) $this->saveDetails['card_expiration'],
				'personal_id' => (string) $this->saveDetails['personal_id'],
				'transaction_exhausted' => true,
				'generate_token_time' => new Mongodloid_Date(time()),
				'auth_number' => (string) ($this->saveDetails['auth_number_token'] ?? $this->saveDetails['auth_number']),
				'reference_txn_id' => (string) $this->saveDetails['reference_txn_id'],
				'four_digits' => (string) $this->saveDetails['four_digits'],
				'card_acquirer' => (string) $this->saveDetails['card_acquirer'],
				'card_brand' => (string) $this->saveDetails['card_brand'],
				'credit_company' => (string) $this->saveDetails['credit_company'],
				'card_type' => (string) $this->saveDetails['card_type'],
				'keepCCDetails' => $this->saveDetails['keepCCDetails'],
				'terminal_name' => $this->saveDetails['terminal_name'],
			)
		];
	}

	protected function buildSinglePaymentArray($params, $options) {
		$creds = $this->getGatewayCredentials();
		$this->amount = $params['amount'];
		$this->operation = self::OPERATION_PAY;
		$this->okPage = $params['ok_page'];
		$this->failPage = $params['fail_page'];
		$this->aid = $params['aid'];

		if ($options['tokenize_on_single_payment']) {
			$this->tokenize = true;
		}
		if (isset($options['installments'])) {
			if (!empty($options['installments']['total_amount'])) {
				$this->installments['amount'] = $this->convertAmountToSend($options['installments']['total_amount']);
				$this->installments['number_of_payments'] = $options['installments']['number_of_payments'] - 1;
				$this->installments['periodical_payments'] = floor($this->installments['amount'] / $options['installments']['number_of_payments']); 	
				$this->installments['first_payment'] = $this->installments['amount'] - ($this->installments['number_of_payments'] * $this->installments['periodical_payments']);
			} else {
				$this->installments['amount'] = $this->amount;
				$this->installments['number_of_payments'] = $options['installments']['number_of_payments'];
			}
//			return $this->getInstallmentXmlStructure($credentials, $xmlParams, $installmentParams, $addonData);
		}

		$ret = [
			'supplier' => $creds['terminal_name'],
			'sum' => $this->amount,
			'TranzilaPW' => $creds['handshake_password'],
		];
		return $ret;
	}

	protected function buildTransactionPost($txId, $additionalParams) {
		$credentials = $this->getGatewayCredentials();
		$this->setAuthHeaders();
		$ret = [
			'terminal_name' => $additionalParams["terminal_name"] ?? $credentials['terminal_name'],
			'transaction_index' => (int) $additionalParams['transaction_index'],
		];
		$this->EndpointUrl = ($additionalParams['api_endpoint'] ?? $this->getEndpointApi()) . self::TRANSACTIONS_PATH;
		return $this->encodeParams($ret);
	}

	protected function convertAmountToSend($amount) {
		return $amount;
	}

	protected function getResponseDetails($result) {
		if (is_string($result)) {
			$response = json_decode($result, true);
		} else {
			$response = $result;
		}
		$transaction = $response['transactions'][0];
		if ($transaction['processor_response_code'] !== "000") {
			return false;
		}
		$this->saveDetails['card_token'] = (string) $transaction['credit_card_token'];
		$this->saveDetails['four_digits'] = (string) substr($transaction['credit_card_token'], -4);
		$this->saveDetails['card_expiration'] = (string) $transaction['expiration_month'] . $transaction['expiration_year'];
		$this->saveDetails['aid'] = (int) $transaction['user_defined_8'];
		$this->saveDetails['personal_id'] = (string) $transaction['credit_card_owner_id'];
		$this->saveDetails['auth_number'] = (string) $transaction['authorization_number'];
		$this->saveDetails['reference_txn_id'] = (string) $transaction['index'];
		$this->saveDetails['card_type'] = (string) $transaction['card_type'];
		$this->saveDetails['credit_company'] = (string) json_decode('"' . trim($transaction['card_description']) . '"');
		$this->saveDetails['card_brand'] = (string) $transaction['card_brand'];
		$this->saveDetails['card_acquirer'] = (string) $transaction['clearing_processor'];
		$this->saveDetails['terminal_name'] = (string) $transaction['child_terminal'];
		$retParams = [
			'uid' => $transaction['uid'],
			'payment_identifier' => $transaction['tempref'], // shovar
			'card_type' => $this->saveDetails['card_type'],
			'credit_company' => $this->saveDetails['credit_company'],
			'card_brand' => $this->saveDetails['card_brand'],
			'card_acquirer' => $this->saveDetails['card_acquirer'],
			'four_digits' => $this->saveDetails['four_digits'],
			'expiration_date' => $this->saveDetails['card_expiration'],
			'action' => $transaction['user_defined_9'] ?? '',
			'transferred_amount' => $this->convertReceivedAmount($transaction['amount']),
			'transaction_status' => $transaction['processor_response_code'],
		];
		if (!empty($retParams['action']) && ($retParams['action'] == 'SinglePayment' || $retParams['action'] == 'SinglePaymentToken')) {
			$this->transactionId = $transaction['index'];
			if ($transaction['number_of_payments']) {
				$retParams['installments'] = [
					'total_amount' => $transaction['amount'],
					'number_of_payments' => $transaction['number_of_payments'],
					'first_payment' => $transaction['first_payment_amount'],
					'periodical_payment' => $transaction['other_payment_amount'],
				];
			}
		}
		
			// if need to tokenize (remarks=SinglePaymentToken) -> trigger j5

		if (!empty($retParams['action']) && $retParams['action'] == 'SinglePaymentToken') {
			$retParams['transferred_amount'] = $transaction['amount'] / 100;
			$retParams['tokenize_status'] = $this->tokenTransaction($transaction, $retParams);
			if ($retParams['tokenize_status'] != '000') {
				$retParams['action'] = 'SinglePayment';
			}
		}

		return $retParams;
	}

	protected function handleTokenRequestError($response, $params) {
		return false;
	}

	protected function isHtmlRedirect() {
		return false;
	}

	protected function isNeedAdjustingRequest() {
		return false;
	}

	protected function isUrlRedirect() {
		return true;
	}

	protected function needRequestForToken() {
		return true;
	}

	protected function tranzilaTransaction($gatewayDetails, $addonData, $chargeAction) {
		$txid = $addonData['txid'];
		$creds = $this->getGatewayCredentials();
		$params = [
			'terminal_name' => $creds['terminal_name'],
			'txn_type' => $chargeAction,
			'reference_txn_id' => (int) $gatewayDetails['reference_txn_id'],
			'authorization_number' => (string) $gatewayDetails['auth_number'],
			'expire_month' => (int) substr($gatewayDetails['card_expiration'], 0, 2),
			'expire_year' => (int) substr($gatewayDetails['card_expiration'], -2),
			'card_number' => $gatewayDetails['card_token'],
			'items' => [
				[
					'name' => null,
//					'type' => 'I',
					'units_number' => 1,
					'unit_price' => (double) abs($gatewayDetails['amount']),
				]
			],
			'remarks' => (string) $txid,
			'user_defined_fields' => [
				[
					'name' => 'action',
					'value' => 'TokenPayment'
				],
				[
					'name' => 'remarks',
					'value' => (string) $txid
				],
				[
					'name' => 'Z_field',
					'value' => (string) $addonData['aid']
				],
			],
		];
		$this->EndpointUrl = $this->getEndpointApi() . self::PAY_PATH;
		return $this->sendPayRequest($params);
	}
	
	protected function pay($gatewayDetails, $addonData) {
		return $this->tranzilaTransaction($gatewayDetails, $addonData, 'force');
	}
	
	protected function credit($gatewayDetails, $addonData) {
		return $this->tranzilaTransaction($gatewayDetails, $addonData, 'credit');
	}

	protected function getTokenRequestType() {
		return Zend_Http_Client::GET;
	}

	public function refundTransaction($rec, $amount) {
		// TODO: use real tranzila refund transaction
		$this->tranzilaTransaction($rec['gateway_details'], $rec, 'refund');
	}
	
	public function queryTransaction($transaction, $options) {
		$transactionId = $transaction['transaction_id'] ?? $transaction['payment_gateway']['transactionId'];
		$txpost = [
			'terminal_name' => $options['terminal_name'] ?? $transaction['payment_gateway']['terminal_name'], 
			'transaction_index' => $transactionId
		];
		if (!empty($options['api_endpoint'])) {
			$txpost['api_endpoint'] = $options['api_endpoint'];
		}
		$req = $this->buildTransactionPost($this->transactionId, $txpost);
		Billrun_Factory::log("Tranzila payment query request: " . print_R($req, 1), Zend_Log::DEBUG);
		$queryResult = Billrun_Util::sendRequest($this->EndpointUrl, $req, Zend_Http_Client::POST, $this->requestHeaders, null, 0);
		return json_decode($queryResult, 1);
	}
	
	protected function updateRedirectUrl($result) {
		$creds = $this->getGatewayCredentials();
		$parts = ['' => ''];
		parse_str($result, $parts);
		$field = $this->getTransactionIdName();
		$this->transactionId = $parts[$field];
		if ($this->operation == self::OPERATION_PAY) {
			$action = $this->tokenize ? 'SinglePaymentToken' : 'SinglePayment';
		} else {
			$action = 'Token';
		}
		$params = [
			'sum' => (string) $this->amount,
			'cred_type' => '1',
			'tranmode' => $this->operation,
			'currency' => '1',
			'success_url_address' => $this->okPage,
			'fail_url_address' => $this->failPage,
			'accessibility' => $creds['accessibility'] ?? '0', // TODO: make pg settings
			'Z_field' => $this->aid,
			'remarks' => $this->aid,
			'action' => $action,
			'hidesum' => '1',
			'lang' => 'il',
			'newprocess' => 0, // 3DS: take from pg settings
			$field => $this->transactionId,
		];
		if ($this->installments && isset($this->installments['first_payment'])) {
			$params['npay'] = $this->installments['number_of_payments'];
			$params['fpay'] = $this->installments['first_payment'];
			$params['spay'] = $this->installments['periodical_payments'];
		}
		if (!empty($creds['template']) && trim($creds['template']) != 'default') {
			$params['template'] = $creds['template'];
		}
		if (!empty($creds['iframe_endpoint'])) {
			$this->redirectUrl = $creds['iframe_endpoint'] . '?' . http_build_query($params);
		} else {
			$this->redirectUrl = self::ENDPOINT_REDIRECT . $creds['terminal_name'] . '/iframenew.php?' . http_build_query($params);
		}
	}

	protected function setRequestParams($params = []) {
		$this->requestParams = [
			'url' => $this->redirectUrl,
			'response_parameters' => [
				'txId',
			],
		];
	}

	protected function validateStructureForCharge($structure) {
		return !empty($structure['card_token']) && !empty($structure['card_expiration']);
	}

	public function authenticateCredentials($params) {
		$this->setAuthHeaders($params);
		$transaction = ['transaction_id' => '1'];
		$result = $this->queryTransaction($transaction, $params);
		return isset($result['rows']);
	}

	public function createRecurringBillingProfile($aid, $gatewayDetails, $params = []): \profile {
		
	}

	public function getSecretFields() {
		return array('secret');
	}

	public function getTransactionIdName() {
		return "thtk";
	}

	public function handleOkPageData($txId) {
		return true;
	}

	public function hasPendingStatus() {
		return false;
	}

	public function updateSessionTransactionId($result) {
		return; // this was set on updateRedirectUrl method
	}

	public function verifyPending($txId) {
		return;
	}
	
	protected function setAuthHeaders($custom_gw = []) {
		if (isset($this->requestHeaders['X-tranzila-api-access-token'])) {
			return;
		}
		$gw = empty($custom_gw) ? $this->getGatewayCredentials() : $custom_gw;
		$time = time();
		$appKey = $gw['appkey'];
		$secret = $gw['secret'];
		$nonce = bin2hex(random_bytes(40)); //actually 80 characters string
		$accessToken = hash_hmac('sha256',$appKey, $secret . $time . $nonce);
		// get token
		$headers = [
			'Accept' => 'application/json, application/xml',
			'Content-Type' => 'application/json',
			'X-tranzila-api-access-token' => $accessToken,
			'X-tranzila-api-app-key' => $appKey,
			'X-tranzila-api-nonce' => $nonce,
			'X-tranzila-api-request-time' => $time,
		];
		$this->requestHeaders = $headers;
	}
	
	protected function encodeParams($params) {
		return json_encode($params);
	}
	
	protected function sendPayRequest($params) {
		$this->setAuthHeaders();
		
		Billrun_Factory::log("Tranzila payment pay request: " . print_R($params, 1), Zend_Log::DEBUG);
		$result = Billrun_Util::sendRequest($this->EndpointUrl, $this->encodeParams($params), Zend_Http_Client::POST, $this->requestHeaders, null, 0);
		Billrun_Factory::log("Tranzila payment pay response: " . print_R($result, 1), Zend_Log::DEBUG);
		$resultObj = json_decode($result, true);
		
		if (!is_array($resultObj) || !isset($resultObj['transaction_result'])) {
			return array('status' => ($resultObj['error_code'] ?? '9998'), 'additional_params' => []);
		}

		$transactionResult = $resultObj['transaction_result'];
		$codeResult = $transactionResult['processor_response_code'] ?? ($resultObj['error_code'] ?? '9999');
		
		if ($codeResult == '000') {
			$this->transactionId = $transactionResult['transaction_id'];
//			$txpost = ['terminal_name' => $params['terminal_name'], 'transaction_index' => $this->transactionId];
//			$req = $this->buildTransactionPost($this->transactionId, $txpost);
//			Billrun_Factory::log("Tranzila payment query request: " . print_R($req, 1), Zend_Log::DEBUG);
//			$queryResult = Billrun_Util::sendRequest($this->EndpointUrl, $req, Zend_Http_Client::POST, $this->requestHeaders, null, 0);
//			Billrun_Factory::log("Tranzila payment query response: " . print_R($queryResult, 1), Zend_Log::DEBUG);
			$result = $this->queryTransaction($transactionResult, $params);
			$additionalParams = $this->getResponseDetails($result);
		} else {
			$additionalParams = [];
		}

		return array('status' => $codeResult, 'additional_params' => $additionalParams);
	}
	
	/**
	 * 
	 * @param array $transaction expire_month, expire_year, card_token, user_defined_8 or aid,
	 * @param type $retParams
	 * @return type
	 */
	public function tokenTransaction($transaction, $retParams) {
		$this->setAuthHeaders();
		$creds = $this->getGatewayCredentials();
		$this->amount = !empty($creds['j5_amount']) ? $creds['j5_amount'] : '1';
		$aid = $transaction['user_defined_8'] ?? ($transaction['remarks'] ?? $transaction['aid']);
		$params = [
			'terminal_name' => $creds['terminal_name'],
			'txn_type' => 'verify',
			'verify_mode' => 5,
			'expire_month' => (int) ($transaction['expire_month'] ?? $transaction['expiration_month']),
			'expire_year' => (int) ($transaction['expire_year'] ?? $transaction['expiration_year']),
			'card_number' => $transaction['card_token'] ?? $transaction['credit_card_token'],
			'items' => [
				[
					'name' => null,
//					'type' => 'I',
					'units_number' => 1,
					'unit_price' => (float) $this->amount,
				]
			],
			'remarks' => (string) $aid,
			'user_defined_fields' => [
				[
					'name' => 'action',
					'value' => 'Token'
				],
				[
					'name' => 'remarks',
					'value' => (string) $aid
				],
			],
		];
		$this->EndpointUrl = $this->getEndpointApi() . self::PAY_PATH;
		Billrun_Factory::log("Tranzila payment query request: " . print_R($params, 1), Zend_Log::DEBUG);
		$queryResult = Billrun_Util::sendRequest($this->EndpointUrl, $this->encodeParams($params), Zend_Http_Client::POST, $this->requestHeaders, null, 0);
		Billrun_Factory::log("Tranzila payment query response: " . print_R($queryResult, 1), Zend_Log::DEBUG);

		$resultObj = json_decode($queryResult, true);
		if (!is_array($resultObj) || !isset($resultObj['transaction_result'])) {
			return $resultObj['error_code'] ?? '9998';
		}

		$transactionResult = $resultObj['transaction_result'];
		$codeResult = $transactionResult['processor_response_code'] ?? ($resultObj['error_code'] ?? '9999');
		return $codeResult;
	}
	
	protected function getEndpointApi($custom_creds = []) {
		if (!empty($this->endpointapi) && !empty($custom_creds)) {
			return $this->endpointapi;
		}
		$creds = empty($custom_creds) ? $this->getGatewayCredentials(): $custom_creds;
		if (empty($creds['api_endpoint'])) {
			$this->endpointapi = self::ENDPOINT_API;
		} else {
			$this->endpointapi = $creds['api_endpoint'];
		}
		return $this->endpointapi;
	}
	
	public function addAdditionalParameters($request) {
		return array(
			'transaction_index' => $request->get('index'),
		);
	}

	public function getDefaultParameters() {
		$params = array("appkey", "secret", "handshake_password", "terminal_name", "j5_amount", "api_endpoint", "iframe_endpoint", "template");
		return $this->rearrangeParametres($params);
	}
	
	protected function convertReceivedAmount($amount) {
		return $amount / 100;
	}

}