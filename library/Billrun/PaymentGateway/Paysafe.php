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
	protected $pendingCodes = "/$^/";
	protected $completionCodes = "/^000$/";
	protected $account;

	protected function __construct() {
		parent::__construct();
                if (Billrun_Factory::config()->isProd()) {
			$this->EndpointUrl = "https://api.paysafe.com";
		} else { // test/dev environment
			$this->EndpointUrl = "https://api.test.paysafe.com";
		}
		$this->subscribers = Billrun_Factory::db()->subscribersCollection();
		$this->account = Billrun_Factory::account();
	}
        //
	public function updateSessionTransactionId() {
            $this->transactionId = $this->billrunToken;
	}
        //
	protected function buildPostArray($aid, $returnUrl, $okPage, $failPage) {
		return false;
	}
        //
	protected function updateRedirectUrl($result) {
		$credentials = $this->getGatewayCredentials();
		$this->htmlForm = $this->buildFormForPopUp($result, $credentials['Username'].":". $credentials['Password']);
	}
        
        protected function buildFormForPopUp($okPage, $publishable_key) {
                $publishable_key_encode= base64_encode($publishable_key);
		return  "<!DOCTYPE html>
					<html>
                                       <head>

                                        <!-- include the Paysafe.js SDK -->
                                        <script src = 'https://hosted.paysafe.com/js/v1/latest/paysafe.min.js'></script>

                                        <!-- external style for the payment fields.internal style must be set using the SDK -->
                                        <style>
                                          .inputField {
                                            border: 1px solid #E5E9EC;
                                            height: 40px;
                                            padding - left: 10px;
                                          }
                                        </style>

                                      </head>

                  
						<body>
                                                <!-- Create divs for the payment fields -->
                                                <div id = 'cardNumber' class='inputField'></div>
                                                <p></p>
                                                <div id = 'expiryDate' class='inputField'></div>
                                                <p></p>
                                                <div id = 'cvv' class='inputField'></div>
                                                <p></p>

                                                <!-- Add a payment button -->
                                                <button type='submit' form='myForm' value='Submit' id = 'pay' type = 'button'> Pay </button>

							
								<script type='text/javascript'>     
                                                                var options = {

                                                                       // select the Paysafe test / sandbox environment
                                                                       environment: 'TEST',

                                                                       // set the CSS selectors to identify the payment field divs above
                                                                       // set the placeholder text to display in these fields
                                                                       fields: {
                                                                         cardNumber: {
                                                                           selector: '#cardNumber',
                                                                           placeholder: 'Card number',
                                                                           separator: ' '
                                                                         },
                                                                         expiryDate: {
                                                                           selector: '#expiryDate',
                                                                           placeholder: 'Expiry date'
                                                                         },
                                                                         cvv: {
                                                                           selector: '#cvv',
                                                                           placeholder: 'CVV',
                                                                           optional: false
                                                                         }
                                                                       }
                                                                     };                                                                
                                                                    paysafe.fields.setup('$publishable_key_encode', options, function(instance, error) {
									document.getElementById('pay').addEventListener('click', function(event, document) {
                                                                            instance.tokenize(function(instance, error, result) {
                                                                                    
                                                                                   console.log(result.token);
                                                                                  
                                                                            });
                                                                            
                                                                        }, false);
                                                                    });
								</script>
							 </form>
							</body>
					</html>";
	}
        
        //
	protected function buildTransactionPost($txId, $additionalParams) {
		$params = $this->getGatewayCredentials();
		$params['txId'] = $txId;
		$params['tid'] = $params['redirect_terminal'];

		return $this->buildInquireQuery($params);
	}
        //
	public function getTransactionIdName() {
		return "tok";
	}
        //
	protected function getResponseDetails($result) {
		if (function_exists("simplexml_load_string")) {
			if (strpos(strtoupper($result), 'HEB')) {
				$result = iconv("utf-8", "iso-8859-8", $result);
			}
			$xmlObj = simplexml_load_string($result);
			// Example to print out status text
			if (!isset($xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->result))
				return false;

			$this->saveDetails['card_token'] = (string) $xmlObj->response->inquireTransactions->row->cardId;
			$this->saveDetails['card_expiration'] = (string) $xmlObj->response->inquireTransactions->row->cardExpiration;
			$this->saveDetails['aid'] = (int) $xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->doDeal->customerData->userData1;
			$this->saveDetails['personal_id'] = (string) $xmlObj->response->inquireTransactions->row->personalId;
			$this->saveDetails['auth_number'] = (string) $xmlObj->response->inquireTransactions->row->authNumber;
			$cardNum = (string) $xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->doDeal->cardNo;
			$retParams['action'] = (string) $xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->doDeal->customerData->userData2;
			$retParams['transferred_amount'] = $this->convertReceivedAmount(floatval($xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->doDeal->total));
			$retParams['transaction_status'] = (string) $xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->doDeal->status;
			$retParams['card_token'] = $this->saveDetails['card_token'];		
			$retParams['personal_id'] = $this->saveDetails['personal_id'];
			$retParams['auth_number'] = $this->saveDetails['auth_number'];
			$fourDigits = substr($cardNum, -4);
			$retParams['four_digits'] = $this->saveDetails['four_digits'] = $fourDigits;
			$retParams['expiration_date'] = (string) $xmlObj->response->inquireTransactions->row->cardExpiration;
			if ($retParams['action'] == 'SinglePayment') {
				$this->transactionId = (string) $xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->tranId;
				$slaveNumber = (string) $xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->doDeal->slaveTerminalNumber;
				$slaveSequence = (string) $xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->doDeal->slaveTerminalSequence;
				$voucherNumber = $slaveNumber . $slaveSequence;
				$retParams['payment_identifier'] = $voucherNumber;
				$creditType = (string) $xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->doDeal->creditType;
				if (!empty((string) $xmlObj->response->inquireTransactions->row->xRem)) {
					$retParams['txid'] = (string) $xmlObj->response->inquireTransactions->row->xRem;
				}
				if ($creditType == 'Payments') {
					$retParams['installments'] = array();
					$retParams['installments']['total_amount'] = $this->convertReceivedAmount(floatval($xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->doDeal->total));
					$retParams['installments']['number_of_payments'] = (int)($xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->doDeal->numberOfPayments) + 1;
					$retParams['installments']['first_payment'] = $this->convertReceivedAmount(floatval($xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->doDeal->firstPayment));
					$retParams['installments']['periodical_payment'] = $this->convertReceivedAmount(floatval($xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->doDeal->periodicalPayment));
				}
			}

			return $retParams;
		} else {
			die("simplexml_load_string function is not support, upgrade PHP version!");
		}
	}

	protected function buildSetQuery() {
		return array(
			'active' => array(
				'name' => $this->billrunName,
				'card_token' => (string) $this->saveDetails['card_token'],
				'card_expiration' => (string) $this->saveDetails['card_expiration'],
				'personal_id' => (string) $this->saveDetails['personal_id'],
				'transaction_exhausted' => true,
				'generate_token_time' => new MongoDate(time()),
				'auth_number' => (string) $this->saveDetails['auth_number'],
				'four_digits' => (string) $this->saveDetails['four_digits'],
			)
		);
	}
        //
	public function getDefaultParameters() {
		$params = array("Username", "Password", "Account");
		return $this->rearrangeParametres($params);
	}

        //
	public function authenticateCredentials($params) {
		$authArray = array(
			'Username' => $params['Username'],
			'Password' => $params['Password'],
                        'Account' => $params['Account']
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
		$credentials = $this->getGatewayCredentials();
		$this->setApiKey($credentials['secret_key']);
		$gatewayDetails['amount'] = $this->convertAmountToSend($gatewayDetails['amount']);
		$result = \Stripe\Charge::create(array(
				"amount" => $gatewayDetails['amount'],
				"currency" => $gatewayDetails['currency'],
				"customer" => $gatewayDetails['customer_id'],
		));
		$status = $this->payResponse($result);

		return $status;
	}
        
        protected function payResponse($result) {
		if (isset($result['id'])) {
			$this->transactionId = $result['id'];
		}

		return $result['status'];
	}

	public function verifyPending($txId) {
		
	}

	public function hasPendingStatus() {
		return false;
	}
	//
	protected function buildInquireQuery($params){
		return array(
			'user' => $params['user'],
			'password' => $params['password'],
			/* Build Ashrait XML to post */
			'int_in' => '<ashrait>
							<request>
							 <language>HEB</language>
							 <command>inquireTransactions</command>
							 <inquireTransactions>
							  <terminalNumber>' . $params['redirect_terminal'] . '</terminalNumber>
							  <mainTerminalNumber/>
							  <queryName>mpiTransaction</queryName>
							  <mid>' . (int)$params['mid'] . '</mid>
							  <mpiTransactionId>' . $params['txId'] . '</mpiTransactionId>
							  <mpiValidation>Token</mpiValidation>
							  <userData1/>
							  <userData2/>
							  <userData3/>
							  <userData4/>
							  <userData5/>
							 </inquireTransactions>
							</request>
					   </ashrait>'
		);
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
        //
	protected function isNeedAdjustingRequest(){
		return true;
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
	
	public function handleOkPageData($txId) {
		return true;
	}
	
	protected function validateStructureForCharge($structure) {
		return !empty($structure['card_token']) && !empty($structure['card_expiration']) && !empty($structure['personal_id']);
	}
	
	protected function handleTokenRequestError($response, $params) {
		return false;
	}

	protected function credit($gatewayDetails, $addonData) {
		$paymentArray = $this->buildPaymentRequset($gatewayDetails, 'Credit', $addonData);
		return $this->sendPaymentRequest($paymentArray);
	}
	//
	protected function sendPaymentRequest($paymentArray) {
		$additionalParams = array();
		$paymentString = http_build_query($paymentArray);
		if (function_exists("curl_init")) {
			$result = Billrun_Util::sendRequest($this->EndpointUrl, $paymentString, Zend_Http_Client::POST, array('Accept-encoding' => 'deflate'), null, 0);
		}
		if (strpos(strtoupper($result), 'HEB')) {
			$result = iconv("utf-8", "iso-8859-8", $result);
		}
		$xmlObj = simplexml_load_string($result);
		$codeResult = (string) $xmlObj->response->result;
		$this->transactionId = (string) $xmlObj->response->tranId;
		$slaveNumber = (string) $xmlObj->response->doDeal->slaveTerminalNumber;
		$slaveSequence = (string) $xmlObj->response->doDeal->slaveTerminalSequence;
		$voucherNumber = $slaveNumber . $slaveSequence;
		if (!empty($voucherNumber)) {
			$additionalParams['payment_identifier'] = $voucherNumber;
		}
		return array('status' => $codeResult, 'additional_params' => $additionalParams);
	}

	protected function buildSinglePaymentArray($params, $options) {
		throw new Exception("Single payment not supported in " . $this->billrunName);
	}
        
        public function createRecurringBillingProfile($aid, $gatewayDetails, $params = []) {
		return false;
	}
        public function adjustOkPage($okPage) {
		$updatedOkPage = $okPage;
		return $updatedOkPage;
	}
}