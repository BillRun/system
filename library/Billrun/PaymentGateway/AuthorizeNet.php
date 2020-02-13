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
class Billrun_PaymentGateway_AuthorizeNet extends Billrun_PaymentGateway {

	protected $billrunName = "AuthorizeNet";
	protected $pendingCodes = "/^4$|E00078|E00055|E00058/";
	protected $customerId;
	protected $completionCodes = "/^1$/";
	protected $rejectionCodes = "/^2$|^3$|^E/";
	protected $actionUrl;
	protected $failureReturnUrl;
	
	const CREDIT_CARD_PAYMENT = 'COMMON.ACCEPT.INAPP.PAYMENT';
	const APPLE_PAY_PAYMENT = 'COMMON.APPLE.INAPP.PAYMENT';

	protected function __construct() {
		if (Billrun_Factory::config()->isProd()) {
			$this->EndpointUrl = "https://api2.authorize.net/xml/v1/request.api";
			$this->actionUrl = 'https://secure.authorize.net/profile/addPayment';
		} else { // test/dev environment
			$this->EndpointUrl = "https://apitest.authorize.net/xml/v1/request.api";
			$this->actionUrl = 'https://test.authorize.net/profile/addPayment';
		}
		$this->account = Billrun_Factory::account();
	}

	public function updateSessionTransactionId() {
		$this->transactionId = $this->customerId;
	}

	protected function buildPostArray($aid, $returnUrl, $okPage, $failPage) {
		$customerProfileId = $this->checkIfCustomerExists($aid);
		if (empty($customerProfileId)) {
			$customerProfileId = $this->createCustomer($aid);
		}
		$this->customerId = $customerProfileId;
		$credentials = $this->getGatewayCredentials();
		$apiLoginId = $credentials['login_id'];
		$transactionKey = $credentials['transaction_key'];
		$okPage = $okPage . '&amp;customer=' . $customerProfileId;

		return $postXml = "<getHostedProfilePageRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
								<merchantAuthentication>
									 <name>$apiLoginId</name>
									 <transactionKey>$transactionKey</transactionKey>
								 </merchantAuthentication>
								<customerProfileId>$customerProfileId</customerProfileId>
								<hostedProfileSettings>
									<setting>
										<settingName>hostedProfileReturnUrl</settingName>
										<settingValue>$okPage</settingValue>
									</setting>
									<setting>
										<settingName>hostedProfileReturnUrlText</settingName>
										<settingValue>Finish</settingValue>
									</setting>
									<setting>
										<settingName>hostedProfilePageBorderVisible</settingName>
										<settingValue>true</settingValue>
									</setting>
								 </hostedProfileSettings>
							 </getHostedProfilePageRequest>";
	}

	protected function updateRedirectUrl($result) {
		if (function_exists("simplexml_load_string")) {
			$xmlObj = @simplexml_load_string($result);
			$token = (string) $xmlObj->token;
			if (empty($token)) {
				$errorMessage = (string) $xmlObj->messages->message->text;
				Billrun_Factory::log("Error: Redirecting to " . $this->returnUrlOnError . ' message: ' . $errorMessage, Zend_Log::ALERT);
				throw new Exception($errorMessage);
			}
			$this->htmlForm = $this->createHtmlRedirection($token);
		} else {
			die("simplexml_load_string function is not support, upgrade PHP version!");
		}
	}

	protected function buildTransactionPost($txId, $additionalParams) {
		$credentials = $this->getGatewayCredentials();
		$apiLoginId = $credentials['login_id'];
		$transactionKey = $credentials['transaction_key'];

		return $customerProfile = "<getCustomerProfileRequest xmlns= 'AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
										<merchantAuthentication>
											<name>$apiLoginId</name>
											<transactionKey>$transactionKey</transactionKey>
										</merchantAuthentication>
										<customerProfileId>$txId</customerProfileId>
									</getCustomerProfileRequest>";
	}

	public function getTransactionIdName() {
		return "customer";
	}

	protected function getResponseDetails($result) {
		if (function_exists("simplexml_load_string")) {
			$xmlObj = @simplexml_load_string($result);
			$resultCode = (string) $xmlObj->messages->resultCode;
			if (($resultCode != 'Ok')) {
				$errorMessage = (string) $xmlObj->messages->message->text;
				Billrun_Factory::log("Error: Redirecting to " . $this->returnUrlOnError . ' message: ' . $errorMessage, Zend_Log::ALERT);
				throw new Exception($errorMessage);
			}
			$customerProfile = $xmlObj->profile;
			$this->saveDetails['aid'] = (int) $customerProfile->merchantCustomerId;
			$this->saveDetails['customer_profile_id'] = (string) $customerProfile->customerProfileId;
			$this->saveDetails['payment_profile_id'] = (string) $customerProfile->paymentProfiles->customerPaymentProfileId;
		} else {
			die("simplexml_load_string function is not support, upgrade PHP version!");
		}
	}

	protected function buildSetQuery() {
		return array(
			'active' => array(
				'name' => $this->billrunName,
				'customer_profile_id' => $this->saveDetails['customer_profile_id'],
				'payment_profile_id' => $this->saveDetails['payment_profile_id'],
				'transaction_exhausted' => true,
				'generate_token_time' => new MongoDate(time())
			)
		);
	}

	public function pay($gatewayDetails, $addonData) {
		$payRequest = $this->buildPaymentRequest($gatewayDetails);
		if (function_exists("curl_init")) {
			$result = Billrun_Util::sendRequest($this->EndpointUrl, $payRequest, Zend_Http_Client::POST, array('Accept-encoding' => 'deflate'), null, 0);
		}
		$status = $this->payResponse($result, $addonData);
		return $status;
	}

		protected function payResponse($result, $addonData = []) {
		$xmlObj = @simplexml_load_string($result);
		$resultCode = (string) $xmlObj->messages->resultCode;
		$additionalParams = [];
		if ($resultCode != 'Ok') {
			$errorMessage = (string) $xmlObj->messages->message->text;
			$status = (string) $xmlObj->messages->message->code;			
		} else {
			$transaction = $xmlObj->transactionResponse;
			$this->transactionId = (string) $transaction->transId;
			$status = (string) $transaction->responseCode;
			$this->savePaymentProfile($xmlObj->profileResponse, $addonData['aid']);
		}
		
		return [
			'status' => $status,
			'additional_params' => $additionalParams,
		];
	}
		
	/**
	 * if customer was created in the request, updates account's payment gateway
	 * 
	 * @param XML $response
	 * @param int $aid
	 */
	protected function savePaymentProfile($profileResponse, $aid) {
		if (!$this->hasCustomerProfile($profileResponse)) {
			return;
		}
		
		$profileId = (string) $profileResponse->customerProfileId;
		$paymentProfileId = (string) $profileResponse->customerPaymentProfileIdList->numericString;
		$this->saveDetails['aid'] = $aid;
		$this->saveDetails['customer_profile_id'] = $profileId;
		$this->saveDetails['payment_profile_id'] = $paymentProfileId;
		$this->savePaymentGateway();
        return $paymentProfileId;
	}
	
	protected function hasCustomerProfile($profileResponse) {
		if (!$profileResponse) {
			return false;
		}
		
		if ($profileResponse->messages->resultCode != 'Ok') {
			Billrun_Factory::log("Invalid profile response from gateway. Response:" . print_R($profileResponse, 1), Billrun_Log::ERR);
			return false;
		}
		
		return true;
	}

	protected function buildPaymentRequest($gatewayDetails) {
		$amount = $gatewayDetails['amount'];
		$root = [
			'tag' => 'createTransactionRequest',
			'attr' => [
				'xmlns' => 'AnetApi/xml/v1/schema/AnetApiSchema.xsd',
			],
		];
		$body = $this->buildAuthenticationBody();
		$body['transactionRequest'] = $this->buildTransactionRequest($amount, $gatewayDetails);
		return $this->encodeRequest($root, $body);
	}
	
	protected function buildTransactionRequest($amount, $gatewayDetails) {
		$transactionRequest = [
			'transactionType' => 'authCaptureTransaction',
			'amount' => $amount,
		];

		$payment = $this->buildTransactionPayment($gatewayDetails);
		if (!empty($payment)) {
			$transactionRequest['payment'] = $payment;
		}
		
		$profile = $this->buildCustomerProfile($gatewayDetails);
		if (!empty($profile)) {
			$transactionRequest['profile'] = $profile;
		}
		
		$customerInfo = $this->buildCustomerInfo($gatewayDetails);
		if (!empty($customerInfo)) {
			$transactionRequest['customer'] = $customerInfo;
		}

		return $transactionRequest;
	}
	
	protected function buildCustomerProfile($gatewayDetails) {
		$customerProfile = Billrun_Util::getIn($gatewayDetails, 'customer_profile_id');
		$paymentProfile = Billrun_Util::getIn($gatewayDetails, 'payment_profile_id');
		$hasProfile = !empty($customerProfile) && !empty($paymentProfile);
		$canCreateProfile = Billrun_Util::getIn($gatewayDetails, 'create_profile', false);
		
		if ($hasProfile) {
			return [
				'customerProfileId' => $customerProfile,
				'paymentProfile' => [
					'paymentProfileId' => $paymentProfile
				],
			];
		}
		
		if ($canCreateProfile) {
			return [
				'createProfile' => true,
			];
		}
		
		return [];
	}
	
	protected function buildCustomerInfo($gatewayDetails) {
		$ret = [];
		$email = Billrun_Util::getIn($gatewayDetails, 'email');
		if (!empty($email)) {
			$ret['email'] = $email;
		}
		return $ret;
	}
	
	protected function buildTransactionPayment($gatewayDetails) {
		$dataDescriptor = Billrun_Util::getIn($gatewayDetails, 'data_descriptor');
		$dataValue = Billrun_Util::getIn($gatewayDetails, 'data_value');
		
		if (empty($dataDescriptor) || empty($dataValue)) {
			return [];
		}
		
		return [
			'opaqueData' => [
				'dataDescriptor' => $dataDescriptor,
				'dataValue' => $dataValue,
			],
		];
	}
	
	protected function buildAuthenticationBody() {
		$credentials = $this->getGatewayCredentials();
		$apiLoginId = $credentials['login_id'];
		$transactionKey = $credentials['transaction_key'];
		
		return [
			'merchantAuthentication' => [
				'name' => $apiLoginId,
				'transactionKey' => $transactionKey,
			],
		];
	}
	
	protected function buildRecurringBillingProfileRequest($aid, $gatewayDetails, $params = []) {
		$root = [
			'tag' => 'createCustomerProfileRequest',
			'attr' => [
				'xmlns' => 'AnetApi/xml/v1/schema/AnetApiSchema.xsd',
			],
		];
		$body = $this->buildAuthenticationBody();
		$body['profile'] = [
			'merchantCustomerId' => $aid,
			'email' => Billrun_Util::getIn($gatewayDetails, 'email', ''),
			'paymentProfiles' => [
				'customerType' => 'individual',
				'payment' => $this->buildTransactionPayment($gatewayDetails),
			],
		];
		if ($this->isApplePayRequest($body)) {
			$body['validationMode'] = 'liveMode';
		}
		return $this->encodeRequest($root, $body);
	}
	
	protected function isApplePayRequest($request) {
		return Billrun_Util::getIn($request, 'profile.paymentProfiles.payment.opaqueData.dataDescriptor') == self::APPLE_PAY_PAYMENT;
	}


	protected function encodeRequest($root, $body, $params = []) {
		$xmlEncoder = new Billrun_Encoder_Xml();
		$params['addHeader'] = false;
		$params['root'] = $root;
		return $xmlEncoder->encode($body, $params);
	}
    
    protected function decodeResponse($result) {
        $xmlObj = @simplexml_load_string($result);
        $resultCode = (string) $xmlObj->messages->resultCode;
		if ($resultCode != 'Ok') {
			$errorMessage = (string) $xmlObj->messages->message->text;
			throw new Exception($errorMessage);
		}
        
        return $xmlObj;
    }

	public function authenticateCredentials($params) {
		$apiLoginId = $params['login_id'];
		$transactionKey = $params['transaction_key'];
		$authRequest = "<authenticateTestRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
							<merchantAuthentication>
								<name>$apiLoginId</name>
								<transactionKey>$transactionKey</transactionKey>
							</merchantAuthentication>
						</authenticateTestRequest>";

		if (function_exists("curl_init")) {
			$result = Billrun_Util::sendRequest($this->EndpointUrl, $authRequest, Zend_Http_Client::POST, array('Accept-encoding' => 'deflate'), null, 0);
		}
		$xmlObj = @simplexml_load_string($result);
		$resultCode = (string) $xmlObj->messages->resultCode;
		if (($resultCode != 'Ok')) {
			$errorMessage = (string) $xmlObj->messages->message->text;
			throw new Exception($errorMessage);
		} else {
			$message = (string) $xmlObj->messages->message->text;
			if ($message != 'Successful.') {
				throw new Exception($message);
			}
		}
		return true;
	}

	public function verifyPending($txId) {
		$response = $this->getCheckoutDetails($txId);
		return $response;
	}

	public function hasPendingStatus() {
		return true;
	}

	/**
	 * Inquire Transaction by transaction Id to check status of a payment.
	 * 
	 * @param string $txId - String that represents the transaction.
	 * @return array - array of the response from Authorize.Net
	 */
	protected function getCheckoutDetails($txId) {
		$credentials = $this->getGatewayCredentials();
		$apiLoginId = $credentials['login_id'];
		$transactionKey = $credentials['transaction_key'];
		$transDetails = "<getTransactionDetailsRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
								<merchantAuthentication>
								  <name>$apiLoginId</name>
								  <transactionKey>$transactionKey</transactionKey>
								</merchantAuthentication>
								<transId>$txId</transId>
						  </getTransactionDetailsRequest>";

		if (function_exists("curl_init")) {
			Billrun_Factory::log("Request for pending payment status: " . $transDetails, Zend_Log::DEBUG);
			$result = Billrun_Util::sendRequest($this->EndpointUrl, $transDetails, Zend_Http_Client::POST, array('Accept-encoding' => 'deflate'), null, 0);
			Billrun_Factory::log("Response for Authorize.Net for pending payment status request: " . $result, Zend_Log::DEBUG);
		}
		$xmlObj = @simplexml_load_string($result);
		$resultCode = (string) $xmlObj->messages->resultCode;
		if ($resultCode != 'Ok') {
			$errorMessage = (string) $xmlObj->messages->message->text;
			throw new Exception($errorMessage);
		}
		$transaction = $xmlObj->transaction;
		$responseCode = (string)$transaction->responseCode;
		return $responseCode;
	}

	public function getDefaultParameters() {
		$params = array("login_id", "transaction_key");
		return $this->rearrangeParametres($params);
	}

	protected function convertAmountToSend($amount) {
		return $amount;
	}

	protected function isNeedAdjustingRequest() {
		return false;
	}

	protected function createCustomer($aid) {
		$credentials = $this->getGatewayCredentials();
		$apiLoginId = $credentials['login_id'];
		$transactionKey = $credentials['transaction_key'];
		$customerRequest = "<createCustomerProfileRequest xmlns= 'AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
								<merchantAuthentication>
									<name>$apiLoginId</name>
									<transactionKey>$transactionKey</transactionKey>
								</merchantAuthentication>
								 <profile>
									 <merchantCustomerId>$aid</merchantCustomerId>
								 </profile>
							</createCustomerProfileRequest>";

		if (function_exists("curl_init")) {
			Billrun_Factory::log("Request for creating customer: " . $customerRequest, Zend_Log::DEBUG);
			$result = Billrun_Util::sendRequest($this->EndpointUrl, $customerRequest, Zend_Http_Client::POST, array('Accept-encoding' => 'deflate'), null, 0);
			Billrun_Factory::log("Response for Authorize.Net for creating customer request: " . $result, Zend_Log::DEBUG);
		}
		if (function_exists("simplexml_load_string")) {
			$xmlObj = @simplexml_load_string($result);
			$customerId = (string) $xmlObj->customerProfileId;
			$resultCode = (string) $xmlObj->messages->resultCode;
			if ($resultCode == 'Error') {
				$errorCode = (string) $xmlObj->messages->message->code;
				$errorMessage = (string) $xmlObj->messages->message->text;	
				if ($errorCode == 'E00039') {
					$errorArray = preg_grep("/^[0-9]+$/", explode(' ', $errorMessage));
					if (count($errorArray) ==! 1) {
						Billrun_Factory::log("Error: Redirecting to " . $this->returnUrlOnError . ' message: ' . $errorMessage, Zend_Log::ALERT);
						throw new Exception($errorMessage); 
					}
					$customerId = current($errorArray);
				}
				if (empty($customerId)) {
					Billrun_Factory::log("Error: Redirecting to " . $this->returnUrlOnError . ' message: ' . $errorMessage, Zend_Log::ALERT);
					throw new Exception($errorMessage);
				}
			}
		} else {
			die("simplexml_load_string function is not support, upgrade PHP version!");
		}

		return $customerId;
	}
	
	public function createRecurringBillingProfile($aid, $gatewayDetails, $params = []) {
		$request = $this->buildRecurringBillingProfileRequest($aid, $gatewayDetails, $params);
		$result = Billrun_Util::sendRequest($this->EndpointUrl, $request, Zend_Http_Client::POST, ['Accept-encoding' => 'deflate'], null, 0);
        $paymentProfileId = $this->recurringBillingProfileResponse($result, $aid, $params);
        return $paymentProfileId ? $paymentProfileId : false;
	}
    
    protected function recurringBillingProfileResponse($result, $aid, $params = []) {
        $response = $this->decodeResponse($result);
		$paymentProfileId = $this->savePaymentProfile($response, $aid);
		return $paymentProfileId;
	}

	protected function createHtmlRedirection($token) {
		return "<!DOCTYPE html>
					<html>
						<body>
							<form id='myForm' method='post' action=$this->actionUrl>
							<input type='hidden' name='token'
							value='$token'/>
							<input type='hidden' name='bloop' value='blibloo'/>
							<input type='submit' value='Continue'/>
							</form>
							<script type='text/javascript'>
							   document.getElementById('myForm').submit();
							</script>
						</body>
					</html>";
	}

	protected function isUrlRedirect() {
		return false;
	}

	protected function isHtmlRedirect() {
		return true;
	}

	protected function needRequestForToken() {
		return true;
	}

	public function isUpdatePgChangesNeeded() {
		return true;
	}
	
	public function deleteAccountInPg($pgAccountDetails) {
		if (empty($pgAccountDetails['payment_id'])) {
			return;
		}
		$credentials = $this->getGatewayCredentials();
		$apiLoginId = $credentials['login_id'];
		$transactionKey = $credentials['transaction_key'];
		$profile_id = $pgAccountDetails['customer_profile_id'];
		$payment_id = $pgAccountDetails['payment_id'];
		$deleteAccountRequest = "<deleteCustomerPaymentProfileRequest xmlns= 'AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
								<merchantAuthentication>
									<name>$apiLoginId</name>
									<transactionKey>$transactionKey</transactionKey>
								</merchantAuthentication>
								<customerProfileId>$profile_id</customerProfileId>
							    <customerPaymentProfileId>$payment_id</customerPaymentProfileId>
							</deleteCustomerPaymentProfileRequest>";

		if (function_exists("curl_init")) {
			$result = Billrun_Util::sendRequest($this->EndpointUrl, $deleteAccountRequest, Zend_Http_Client::POST, array('Accept-encoding' => 'deflate'), null, 0);
		}
		if (function_exists("simplexml_load_string")) {
			$xmlObj = @simplexml_load_string($result);
			$resultCode = (string) $xmlObj->messages->resultCode;
			if (($resultCode != 'Ok')) {
				$errorCode = (string) $xmlObj->messages->message->code;
				if ($errorCode == 'E00040') {	// Error: Record not found(non-existing customer)
					return;
				}
				$errorMessage = (string) $xmlObj->messages->message->text;
				throw new Exception($errorMessage);
			}
		} else {
			die("simplexml_load_string function is not support, upgrade PHP version!");
		}
	}
	
	public function getNeededParamsAccountUpdate($account) {
		return array('customer_profile_id' => $account['customer_profile_id'], 'payment_id' => isset($account['payment_profile_id']) ? $account['payment_profile_id'] : '');
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
			if ($gateway['name'] == 'AuthorizeNet') {
				$customerProfileId = $gateway['params']['customer_profile_id'];
			}
		}
		
		return $customerProfileId;
	}
	
	public function handleOkPageData($txId) {
		$credentials = $this->getGatewayCredentials();
		$apiLoginId = $credentials['login_id'];
		$transactionKey = $credentials['transaction_key'];
		$customerProfileRequest = "<getCustomerProfileRequest xmlns= 'AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
										<merchantAuthentication>
											<name>$apiLoginId</name>
											<transactionKey>$transactionKey</transactionKey>
										</merchantAuthentication>
										<customerProfileId>$txId</customerProfileId>
									</getCustomerProfileRequest>";
		
		if (function_exists("curl_init")) {
			$result = Billrun_Util::sendRequest($this->EndpointUrl, $customerProfileRequest, Zend_Http_Client::POST, array('Accept-encoding' => 'deflate'), null, 0);
		}
		
		if (function_exists("simplexml_load_string")) {
			$xmlObj = @simplexml_load_string($result);
			$resultCode = (string) $xmlObj->messages->resultCode;
			if (($resultCode != 'Ok')) {
				$errorMessage = (string) $xmlObj->messages->message->text;
				Billrun_Factory::log("Error: Redirecting to " . $this->returnUrlOnError . ' message: ' . $errorMessage, Zend_Log::ALERT);
				throw new Exception($errorMessage);
			}
			$customerProfile = $xmlObj->profile;
			$aid = (int) $customerProfile->merchantCustomerId;
			$paymentProfileId = (string) $customerProfile->paymentProfiles->customerPaymentProfileId;
		} else {
			die("simplexml_load_string function is not support, upgrade PHP version!");
		}
		
		if (empty($paymentProfileId)) {	
			$index = 0;
			$account = new Billrun_Account_Db();
			$account->load(array('aid' => $aid));
			$accountPg = $account->payment_gateway;
			$setValues['payment_gateway']['active'] = array();
			if (!isset($accountPg['former'])) { 
				$previousPg = array();
			} else {
				$previousPg = $accountPg['former'];
				$counter = 0;
				foreach ($previousPg as $gateway) {
					if ($gateway['name'] == 'AuthorizeNet') {
						unset($previousPg[$counter]);
						$index = $counter;
					} 
					$counter++;
				}
			}
			$currentPg = array(
				'name' => 'AuthorizeNet',
				'params' => array('customer_profile_id' => $txId)
			);
			$previousPg[$index] = $currentPg;
			$setValues['payment_gateway']['former'] = $previousPg;
			$account->closeAndNew($setValues);
			$failureReturnUrl = $account->tenant_return_url;
			return $failureReturnUrl;
		} 
		
		return true;
	}
	
	/**
	 * Returns True if there is a need to update the account's payment gateway structure.
	 * 
	 * @param array $params - array of gateway parameters
	 * @return Boolean - True if update needed.
	 */
	public function needUpdateFormerGateway($params) {
		return !empty($params['customer_profile_id']);
	}
	protected function validateStructureForCharge($structure) {
		return (!empty($structure['customer_profile_id']) && !empty($structure['payment_profile_id'])) ||
			(!empty($structure['data_descriptor']) && !empty($structure['data_value']));
	}
	
	protected function handleTokenRequestError($response, $params) {
		$xmlObj = @simplexml_load_string($response);
		$resultCode = (string) $xmlObj->messages->resultCode;
		if (($resultCode == 'Error')) {
			$errorCode = (string) $xmlObj->messages->message->code;
			if ($errorCode == 'E00040') {
				$subscribersColl = Billrun_Factory::db()->subscribersCollection();
				$accountQuery = Billrun_Utils_Mongo::getDateBoundQuery();
				$accountQuery['type'] = 'account';
				$accountQuery['aid'] = $params['aid'];	
				$subscribersColl->update($accountQuery, array('$pull' => array('payment_gateway.former' => array('name' => array('$in' => array($this->billrunName))))));			
				return true;
			}
		}
		return false;
	}
	
	protected function buildSinglePaymentArray($params, $options) {
		throw new Exception("Single payment not supported in " . $this->billrunName);
	}
}
