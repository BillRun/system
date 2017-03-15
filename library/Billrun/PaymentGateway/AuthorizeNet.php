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
	protected $pendingCodes = "/^4$/";
	protected $customerId;
	protected $completionCodes = "/^1$/";
	protected $rejectionCodes = "/^2$|^3$/";
	protected $actionUrl;
	protected $failureReturnUrl;

	protected function __construct() {
		if (Billrun_Factory::config()->isProd()) {
			$this->EndpointUrl = "https://api2.authorize.net/xml/v1/request.api";
			$this->actionUrl = 'https://secure.authorize.net/profile/addPayment';
		} else { // test/dev environment
			$this->EndpointUrl = "https://apitest.authorize.net/xml/v1/request.api";
			$this->actionUrl = 'https://test.authorize.net/profile/addPayment';
		}
	}

	public function updateSessionTransactionId() {
		$this->transactionId = $this->customerId;
	}

	protected function buildPostArray($aid, $returnUrl, $okPage) {
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
			$xmlObj = simplexml_load_string($result);
			$token = (string) $xmlObj->token;
			if (empty($token)) {
				$errorMessage = (string) $xmlObj->messages->message->text;
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
			'payment_gateway.active' => array(
				'name' => $this->billrunName,
				'customer_profile_id' => $this->saveDetails['customer_profile_id'],
				'payment_profile_id' => $this->saveDetails['payment_profile_id'],
				'transaction_exhausted' => true,
				'generate_token_time' => new MongoDate(time())
			)
		);
	}

	public function pay($gatewayDetails) {
		$payXml = $this->buildPaymentRequset($gatewayDetails);
		if (function_exists("curl_init")) {
			$result = Billrun_Util::sendRequest($this->EndpointUrl, $payXml, Zend_Http_Client::POST, array('Accept-encoding' => 'deflate'), null, 0);
		}
		$status = $this->payResponse($result);
		return $status;
	}

	protected function payResponse($result) {
		$xmlObj = @simplexml_load_string($result);
		$resultCode = (string) $xmlObj->messages->resultCode;
		if (($resultCode != 'Ok')) {
			$errorMessage = (string) $xmlObj->messages->message->text;
			throw new Exception($errorMessage);
		}
		$transaction = $xmlObj->transactionResponse;
		$this->transactionId = (string) $transaction->transId;
		$responseCode = (string) $transaction->responseCode;
		return $responseCode;
	}

	protected function buildPaymentRequset($gatewayDetails) {
		$credentials = $this->getGatewayCredentials();
		$apiLoginId = $credentials['login_id'];
		$transactionKey = $credentials['transaction_key'];
		$amount = $gatewayDetails['amount'];
		$customerProfile = $gatewayDetails['customer_profile_id'];
		$paymentProfile = $gatewayDetails['payment_profile_id'];

		return $payXml = "<createTransactionRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
							<merchantAuthentication>
							  <name>$apiLoginId</name>
							  <transactionKey>$transactionKey</transactionKey>
							</merchantAuthentication>
							<transactionRequest>
							  <transactionType>authCaptureTransaction</transactionType>
							  <amount>$amount</amount>
							  <profile>
								<customerProfileId>$customerProfile</customerProfileId>
								<paymentProfile>
								  <paymentProfileId>$paymentProfile</paymentProfileId>
								</paymentProfile>
							  </profile>
							</transactionRequest>
						  </createTransactionRequest>";
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
		$xmlObj = simplexml_load_string($result);
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
	 * @return array - array of the response from PayPal
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
			$result = Billrun_Util::sendRequest($this->EndpointUrl, $transDetails, Zend_Http_Client::POST, array('Accept-encoding' => 'deflate'), null, 0);
		}
		$xmlObj = simplexml_load_string($result);
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
			$result = Billrun_Util::sendRequest($this->EndpointUrl, $customerRequest, Zend_Http_Client::POST, array('Accept-encoding' => 'deflate'), null, 0);
		}
		if (function_exists("simplexml_load_string")) {
			$xmlObj = simplexml_load_string($result);
			$customerId = (string) $xmlObj->customerProfileId;
			if (empty($customerId)) {
				$errorMessage = (string) $xmlObj->messages->message->text;
				throw new Exception($errorMessage);
			}
		} else {
			die("simplexml_load_string function is not support, upgrade PHP version!");
		}

		return $customerId;
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
			$xmlObj = simplexml_load_string($result);
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
		} else {
			die("simplexml_load_string function is not support, upgrade PHP version!");
		}
	}
	
	public function getNeededParamsAccountUpdate($account) {
		return array('customer_profile_id' => $account['customer_profile_id'], 'payment_id' => $account['payment_profile_id']);
	}
	
	protected function checkIfCustomerExists ($aid) {
		$customerProfileId = '';
		$accountQuery = Billrun_Utils_Mongo::getDateBoundQuery();
		$accountQuery['type'] = 'account';
		$accountQuery['aid'] = $aid;
		$subscribers = Billrun_Factory::db()->subscribersCollection();
		$account = $subscribers->query($accountQuery)->cursor()->current();
		$formerGateways = $account['payment_gateway.former'];
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

}
