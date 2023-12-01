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
class Billrun_PaymentGateway_CreditGuard extends Billrun_PaymentGateway {

	protected $conf;
	protected $billrunName = "CreditGuard";
	protected $pendingCodes = "/$^/";
	protected $completionCodes = "/^000$/";
	protected $account;
        
	protected function __construct($instanceName =  null) {
		parent::__construct($instanceName);
		$this->EndpointUrl = $this->getGatewayCredentials()['endpoint_url'] ?? null;
	}

	public function updateSessionTransactionId($result) {
		$url_array = parse_url($this->redirectUrl);
		$str_response = array();
		parse_str($url_array['query'], $str_response);
		$this->transactionId = $str_response['txId'];
	}

	protected function buildPostArray($aid, $returnUrl, $okPage, $failPage) {
		$credentials = $this->getGatewayCredentials();
		$xmlParams['version'] = $credentials['version'] ?? '2000';
		$xmlParams['mpiValidation'] = 'Verify';
		$xmlParams['transactionType'] = 'RecurringDebit';
		$xmlParams['userData2'] = '';
		$xmlParams['aid'] = $aid;
		$xmlParams['ok_page'] = $okPage;
		$xmlParams['return_url'] = $returnUrl;
		$xmlParams['amount'] = (int) Billrun_Factory::config()->getConfigValue('CG.conf.amount', 100);
		$account = Billrun_Factory::account();
		$account->loadAccountForQuery(array('aid' => (int)$aid));
		$xmlParams['language'] = isset($account->pay_page_lang) ? $account->pay_page_lang : "HEB";
		$xmlParams['addFailPage'] = $failPage ? '<errorUrl>' . $failPage  . '</errorUrl>' : '';

		$customParams = $this->getGatewayCustomParams();


		return $this->getXmlStructureByParams($credentials, $xmlParams, ( !empty($customParams) ? $customParams : [])) ;
	}

	protected function updateRedirectUrl($result) {
		if (function_exists("simplexml_load_string")) {
			if (strpos(strtoupper($result), 'HEB')) {
				$result = iconv("utf-8", "iso-8859-8", $result);
			}
			$xmlObj = simplexml_load_string($result);

			if (isset($xmlObj->response->doDeal->mpiHostedPageUrl)) {

				$this->redirectUrl = (string)$xmlObj->response->doDeal->mpiHostedPageUrl;
				$this->setRequestParams();
			} else {
				Billrun_Factory::log("Error: " . 'Error Code: ' . $xmlObj->response->result .
					'Message: ' . $xmlObj->response->message .
					'Addition Info: ' . $xmlObj->response->additionalInfo, Zend_Log::ALERT);
				throw new Exception('Can\'t Create Transaction');
			}
		} else {
			die("simplexml_load_string function is not support, upgrade PHP version!");
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

	protected function buildTransactionPost($txId, $additionalParams) {
		$params = $this->getGatewayCredentials();
		$params['txId'] = $txId;
		$params['tid'] = $params['redirect_terminal'];
		if ($additionalParams['keepCCDetails']) {
			$this->saveDetails['keepCCDetails'] = $additionalParams['keepCCDetails'];
		}

		return $this->buildInquireQuery($params, $additionalParams['terminal'] ?? 'redirect_terminal');
	}

	public function getTransactionIdName() {
		return "txId";
	}

	protected function getResponseDetails($result) {
		if (function_exists("simplexml_load_string")) {
			if (strpos(strtoupper($result), 'HEB')) {
				$result = iconv("utf-8", "iso-8859-8", $result);
			}
			$xmlObj = simplexml_load_string($result);
			// Example to print out status text
			if (!isset($xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->result) || 
					(string) $xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->result !== '000') {
				return false;
			}

			$this->saveDetails['card_token'] = (string) $xmlObj->response->inquireTransactions->row->cardId;
			$this->saveDetails['card_expiration'] = (string) $xmlObj->response->inquireTransactions->row->cardExpiration;
			$this->saveDetails['aid'] = (int) $xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->doDeal->customerData->userData1;
			$this->saveDetails['personal_id'] = (string) $xmlObj->response->inquireTransactions->row->personalId;
			$this->saveDetails['auth_number'] = (string) $xmlObj->response->inquireTransactions->row->authNumber;
			$this->saveDetails['card_type'] = (string) $xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->doDeal->cardType->attributes()->code;
			$this->saveDetails['credit_company'] = (string) $xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->doDeal->creditCompany->attributes()->code;
			$this->saveDetails['card_brand'] = (string) $xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->doDeal->cardBrand->attributes()->code;
			$this->saveDetails['card_acquirer'] = (string) $xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->doDeal->cardAcquirer->attributes()->code;
			$cardNum = (string) $xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->doDeal->cardNo;
			$retParams['action'] = (string) $xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->doDeal->customerData->userData2;
			$retParams['transferred_amount'] = $this->convertReceivedAmount(floatval($xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->doDeal->total));
			$retParams['transaction_status'] = (string) $xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->doDeal->status;
			$retParams['card_token'] = $this->saveDetails['card_token'];		
			$retParams['personal_id'] = $this->saveDetails['personal_id'];
			$retParams['auth_number'] = $this->saveDetails['auth_number'];
			$retParams['card_type'] = $this->saveDetails['card_type'];
			$retParams['credit_company'] = $this->saveDetails['credit_company'];
			$retParams['card_brand'] = $this->saveDetails['card_brand'];
			$retParams['card_acquirer'] = $this->saveDetails['card_acquirer'];
			$fourDigits = substr($cardNum, -4);
			$retParams['four_digits'] = $this->saveDetails['four_digits'] = $fourDigits;
			$retParams['expiration_date'] = (string) $xmlObj->response->inquireTransactions->row->cardExpiration;
			$retParams['terminal_number'] = (string) $xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->doDeal->terminalNumber;
			$retParams['uid'] = (string) $xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->doDeal->ashraitEmvData->uid;
			if ($retParams['action'] == 'SinglePayment' || $retParams['action'] == 'SinglePaymentToken') {
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

			if ($this->saveDetails['keepCCDetails']) {
				$retParams['action'] = 'SinglePaymentToken';
			}
			
			if ($retParams['action'] == 'SinglePaymentToken') {
				$j5_response_xml = $this->sendJ5Request($this->saveDetails['aid'], $this->saveDetails, 'RecurringDebit');
				$j5_response = simplexml_load_string($j5_response_xml);
				if (!isset($j5_response->response->result) ||
						(string) $j5_response->response->result !== '000') {
					$retParams['action'] = 'SinglePayment'; // fallback to single payment so we will not save payment gateway into account
				} else {
					$this->saveDetails['auth_number_token'] = (string) $j5_response->response->doDeal->authNumber;
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
				'instance_name' => $this->instanceName,
				'card_token' => (string) $this->saveDetails['card_token'],
				'card_expiration' => (string) $this->saveDetails['card_expiration'],
				'personal_id' => (string) $this->saveDetails['personal_id'],
				'transaction_exhausted' => true,
				'generate_token_time' => new Mongodloid_Date(time()),
				'auth_number' => (string) ($this->saveDetails['auth_number_token'] ?? $this->saveDetails['auth_number']),
				'four_digits' => (string) $this->saveDetails['four_digits'],
				'card_acquirer' => (string) $this->saveDetails['card_acquirer'],
				'card_brand' => (string) $this->saveDetails['card_brand'],
				'credit_company' => (string) $this->saveDetails['credit_company'],
				'card_type' => (string) $this->saveDetails['card_type'],
				'keepCCDetails' => $this->saveDetails['keepCCDetails'],
			)
		);
	}

	public function getDefaultParameters() {
		$params = array("user", "password", "redirect_terminal", "charging_terminal", "mid", "endpoint_url", "version", "custom_style", "custom_text", "custom_style_singlepayment", "custom_text_singlepayment", "ancestor_urls");
		return $this->rearrangeParametres($params);
	}
	
	public function getReceiverParameters() {
		$params = array("host", "user", "password", "remote_directory");
		return $this->rearrangeParametres($params);
	}

	public function getExportParameters() {
		$params = array("server", "user", "pw", "dir");
		return $this->rearrangeParametres($params);
	}

	public function authenticateCredentials($params) {
		$params['txId'] = 1;
		$authArray = $this->buildInquireQuery($params);
		$authString = http_build_query($authArray);
		if (function_exists("curl_init")) {
			Billrun_Factory::log("Sending to Credit Guard (authenticateCredentials): " . $params['endpoint_url'] . ' ' . $authString, Zend_Log::DEBUG);
			$result = Billrun_Util::sendRequest($params['endpoint_url'], $authString, Zend_Http_Client::POST, array('Accept-encoding' => 'deflate'), null, 0);
		}
		if (strpos(strtoupper($result), 'HEB')) {
			$result = iconv("utf-8", "iso-8859-8", $result);
		}
		$xmlObj = simplexml_load_string($result);
		$codeResult = (string) $xmlObj->response->result;
		Billrun_Factory::log("Credit Guard response (authenticateCredentials):" . print_r($xmlObj, 1), Zend_Log::DEBUG);
		if ($codeResult == "405" || empty($result)) {
			Billrun_Factory::log("Credit Guard error (authenticateCredentials):" . print_r($xmlObj, 1), Zend_Log::ERR);
			return false;
		} else {
			return true;
		}
	}

	public function pay($gatewayDetails, $addonData) {
		$paymentArray = $this->buildPaymentRequset($gatewayDetails, 'RecurringDebit', $addonData);
		return $this->sendPaymentRequest($paymentArray);
	}

	protected function buildPaymentRequset($gatewayDetails, $transactionType, $addonData) {
		$credentials = $this->getGatewayCredentials();
		$customParams = $this->getGatewayCustomParams();
		$gatewayDetails['amount'] = $this->convertAmountToSend($gatewayDetails['amount']);
		$ZParameter = '';
		if (!empty($customParams['send_z_param'])) {
			$aidStringVal = strval($addonData['aid']);
			$addonData['aid'] = $this->addLeadingZero($aidStringVal);
			if (strlen($aidStringVal) > 8) { // Sent tag addonData(Z parameter) to CG must be 2-8 digits
				Billrun_Factory::log("Z parameter " . $addonData['aid'] . " sent to Credit Guard is larger than 8 digits", Zend_Log::NOTICE);
			}
			$ZParameter = !empty($addonData['aid']) ? '<addonData>' . $addonData['aid']  . '</addonData>' : '';
		}
		$this->transactionId = $addonData['txid'];
                $version = $credentials['version'] ?? '2000';
		return $post_array = array(
			'user' => $credentials['user'],
			'password' => $credentials['password'],
			/* Build Ashrait XML to post */
			'int_in' => '<ashrait>
								<request>
								<command>doDeal</command>
								<requestId>23468</requestId>
								<version>' . $version . '</version>
								<language>Heb</language>
								<mayBeDuplicate>0</mayBeDuplicate>
									<doDeal>
										<terminalNumber>' . $credentials['charging_terminal'] . '</terminalNumber>
										<cardId>' . $gatewayDetails['card_token'] . '</cardId>
										<cardExpiration>' . $gatewayDetails['card_expiration'] . '</cardExpiration>
										<creditType>RegularCredit</creditType>
										<currency>' . $gatewayDetails['currency'] . '</currency>
										<transactionCode>Phone</transactionCode>
										<transactionType>' . $transactionType . '</transactionType>
										<total>' . abs($gatewayDetails['amount']) . '</total>
										' . ((!empty($gatewayDetails['auth_number']) && $gatewayDetails['amount'] > 0) ? '<authNumber>' . $gatewayDetails['auth_number'] . '</authNumber>' : '') . '
										<user>' . $this->transactionId . '</user>
										 ' . $ZParameter . '
										<validation>AutoComm</validation>
										 <customerData>
											<userData1>' . $addonData['aid'] . '</userData1>
	                                     </customerData>
									</doDeal>
								</request>
						</ashrait>'
		);
	}

	public function verifyPending($txId) {
		
	}

	public function hasPendingStatus() {
		return false;
	}
	
	protected function buildInquireQuery($params, $terminal = 'redirect_terminal') {
                $version = $params['version'] ?? '2000';
		return array(
			'user' => $params['user'],
			'password' => $params['password'],
			/* Build Ashrait XML to post */
			'int_in' => '<ashrait>
							<request>
							 <language>HEB</language>
                                                         <version>' . $version . '</version>
							 <command>inquireTransactions</command>
							 <inquireTransactions>
							  <terminalNumber>' . ($params[$terminal] ?? $params['redirect_terminal']) . '</terminalNumber>
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

	protected function isNeedAdjustingRequest(){
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
		return !empty($structure['card_token']) && !empty($structure['card_expiration']);
	}
	
	protected function handleTokenRequestError($response, $params) {
		return false;
	}

	protected function credit($gatewayDetails, $addonData) {
		$paymentArray = $this->buildPaymentRequset($gatewayDetails, 'Credit', $addonData);
		return $this->sendPaymentRequest($paymentArray);
	}
	
	protected function sendPaymentRequest($paymentArray) {
		$additionalParams = array();
		$codeResult = '';
		$paymentString = http_build_query($paymentArray);
		if (function_exists("curl_init")) {
			Billrun_Factory::log("Creditguard payment request: " . print_R($paymentArray, 1), Zend_Log::DEBUG);
			$result = Billrun_Util::sendRequest($this->EndpointUrl, $paymentString, Zend_Http_Client::POST, array('Accept-encoding' => 'deflate'), null, 0);
			Billrun_Factory::log("Creditguard payment response: " . print_R($result, 1), Zend_Log::DEBUG);
		}
		if (strpos(strtoupper($result), 'HEB')) {
			$result = iconv("utf-8", "iso-8859-8", $result);
		}
		$xmlObj = simplexml_load_string($result);
		if ($xmlObj !== false) {
			$codeResult = (string) $xmlObj->response->result;
			$this->transactionId = (string) $xmlObj->response->tranId;
			$slaveNumber = (string) $xmlObj->response->doDeal->slaveTerminalNumber;
			$slaveSequence = (string) $xmlObj->response->doDeal->slaveTerminalSequence;
			$voucherNumber = $slaveNumber . $slaveSequence;
			if (!empty($voucherNumber)) {
				$additionalParams['payment_identifier'] = $voucherNumber;
			}
			$additionalParams['card_acquirer'] = $xmlObj->response->doDeal->cardAcquirer ? current($xmlObj->response->doDeal->cardAcquirer->attributes()->code) : '';
			$additionalParams['card_brand'] = $xmlObj->response->doDeal->cardBrand ? current($xmlObj->response->doDeal->cardBrand->attributes()->code) : '';
			$additionalParams['credit_company'] = $xmlObj->response->doDeal->creditCompany ? current($xmlObj->response->doDeal->creditCompany->attributes()->code) : '';
			$additionalParams['card_type'] = $xmlObj->response->doDeal->cardType ? current($xmlObj->response->doDeal->cardType->attributes()->code) : '';
			$additionalParams['uid'] = $xmlObj->response->doDeal->ashraitEmvData->uid ? (string) $xmlObj->response->doDeal->ashraitEmvData->uid : '';
		}	
		return array('status' => $codeResult, 'additional_params' => $additionalParams);
	}
	
	public function handleTransactionRejectionCases($responseFromGateway, $paymentParams) {
		if ($responseFromGateway['stage'] != 'Rejected') {
			return false;
		}
		$cgConfig = Billrun_Factory::config()->getConfigValue('creditguard');
		$gatewayDetails = $paymentParams['gateway_details'];
		$updatedPaymentParams = $paymentParams;
		if ($responseFromGateway['status'] == $cgConfig['card_expiration_rejection_code'] && $this->isCreditCardExpired($gatewayDetails['card_expiration'])) {
			$updatedPaymentParams['gateway_details']['card_expiration'] = $gatewayDetails['card_expiration'] = $this->getCardExpiration($gatewayDetails['card_expiration']);
			if(!$this->updateAccountCardExpiration($paymentParams, $gatewayDetails)){
					return false;
			}
			return $updatedPaymentParams;
		}
		
		return false;
	}
	
	public function isCreditCardExpired($expiration) {
		$cgConfig = Billrun_Factory::config()->getConfigValue('creditguard');
		$oldestCardExpiration = $cgConfig['oldest_card_expiration'];
		$expires = \DateTime::createFromFormat('my', $expiration);
		$dateTooOld = new DateTime($oldestCardExpiration);
		if ($expires < $dateTooOld) {
			Billrun_Factory::log("Expiration date " . $expires->date . " is too old", Zend_Log::DEBUG);
			return false;
		}
		
		return $expires < new DateTime();
	}

	protected function buildSinglePaymentArray($params, $options) {
		$credentials = $this->getGatewayCredentials();
		$customParams = $this->getGatewayCustomParams();
		$addonData = array();
		$xmlParams['aid'] = $addonData['aid'] = $params['aid'];
		$xmlParams['version'] = $credentials['version'] ?? '2000';
		$xmlParams['mpiValidation'] = 'AutoComm';
		$xmlParams['userData2'] = $options['tokenize_on_single_payment'] ? 'SinglePaymentToken' : 'SinglePayment';
		$xmlParams['tokenize_option'] = $options['tokenize_option'] ?? false;
		if (!empty($customParams['send_z_param'])) {
			$aidStringVal = strval($addonData['aid']);
			$addonData['aid'] = $this->addLeadingZero($aidStringVal);
			if (strlen($aidStringVal) > 8) { // Sent tag addonData(Z parameter) to CG must be 2-8 digits
				Billrun_Factory::log("Z parameter " . $addonData['aid'] . " sent to Credit Guard is larger than 8 digits", Zend_Log::NOTICE);
			}
		} else {
			unset($addonData['aid']);
		}
		$addonData['txid'] = $params['txid'];
		$xmlParams['ok_page'] = $params['ok_page'];
		$xmlParams['return_url'] = $params['return_url'];
		$xmlParams['amount'] = $this->convertAmountToSend($params['amount']);
		$query = array('aid' => (int) $params['aid']);
		$account = $this->account->loadAccountForQuery($query);
		$xmlParams['language'] = isset($account['pay_page_lang']) ? $account['pay_page_lang'] : "HEB";
		$xmlParams['addFailPage'] = $params['fail_page'] ? '<errorUrl>' . $params['fail_page']  . '</errorUrl>' : '';
		if (isset($options['installments'])) {
			if (!empty($options['installments']['total_amount'])) {
			$installmentParams['amount'] = $this->convertAmountToSend($options['installments']['total_amount']);
			$installmentParams['number_of_payments'] = $options['installments']['number_of_payments'] - 1;
			$installmentParams['periodical_payments'] = floor($installmentParams['amount'] / $options['installments']['number_of_payments']); 	
			$installmentParams['first_payment'] = $installmentParams['amount'] - ($installmentParams['number_of_payments'] * $installmentParams['periodical_payments']);
			} else {
				$installmentParams['amount'] = $xmlParams['amount'];
				$installmentParams['number_of_payments'] = $options['installments']['number_of_payments'];
			}
			return $this->getInstallmentXmlStructure($credentials, $xmlParams, $installmentParams, $addonData);
		}

		//add spesific  configration that to  be applies on each new payment page
		if(!empty($customParams) && is_array($customParams)) {
			$addonData = array_merge($customParams,$addonData);
		}

		return $this->getXmlStructureByParams($credentials, $xmlParams, $addonData);
	}
	
	protected function getXmlStructureByParams($credentials, $xmlParams, $addonData = array()) {
		$ppsConfig  = $this->getPPSConfigJSON($xmlParams);
		$XParameter = !empty($addonData['txid']) ? '<user>' . $addonData['txid']  . '</user>' : '';
		$ZParameter = !empty($addonData['aid']) ? '<addonData>' . $addonData['aid']  . '</addonData>' : '';
		$ashraitEmvData = '<ashraitEmvData>
						<recurringTotalNo>999</recurringTotalNo>
						<recurringTotalSum></recurringTotalSum>
						<recurringFrequency>04</recurringFrequency>
					</ashraitEmvData>';

		return array(
			'user' => $credentials['user'],
			'password' => $credentials['password'],
			/* Build Ashrait XML to post */
			'int_in' => '<ashrait>                                      
							<request>
								 <version>' . $xmlParams['version'] . '</version>
								 <language>' . $xmlParams['language'] . '</language>
								 <dateTime/>
								 <command>doDeal</command>
								 <doDeal>
										  <successUrl>' . $xmlParams['ok_page'] . '</successUrl>
										  '. $xmlParams['addFailPage']  .'
										  <terminalNumber>' . $credentials['redirect_terminal'] . '</terminalNumber>
										 ' . $XParameter . '
										 ' . $ZParameter . '
										  <mainTerminalNumber/>
										  <cardNo>CGMPI</cardNo>
										  <total>' . $xmlParams['amount'] . '</total>
										  <transactionType>' . ($xmlParams['transactionType'] ? $xmlParams['transactionType'] : 'Debit') . '</transactionType>
										  <creditType>RegularCredit</creditType>
										  <currency>ILS</currency>
										  <transactionCode>Phone</transactionCode>
										  <authNumber/>
										  <numberOfPayments/>
										  <firstPayment/>
										  <periodicalPayment/>
										  <validation>TxnSetup</validation>
										  <dealerNumber/>
										  <mid>' . (int) $credentials['mid'] . '</mid>
										  <uniqueid>' . time() . rand(100, 1000) . '</uniqueid>
										  <mpiValidation>' . $xmlParams['mpiValidation'] . '</mpiValidation>' .
											($xmlParams['transactionType'] == 'RecurringDebit' ?  $ashraitEmvData : '' ) . '
										  <customerData>
										   <userData1>' . $xmlParams['aid'] . '</userData1>
										   <userData2>' . $xmlParams['userData2'] . '</userData2>
										   <userData3/>
										   <userData4/>
										   <userData5/>
										   <userData6/>
										   <userData7/>
										   <userData8/>
										   <userData9/>
										   <userData10/>
										  </customerData>
										  '. (!empty($ppsConfig) ?
										  '<paymentPageData>
											<ppsJSONConfig>
												'.$ppsConfig.'
											</ppsJSONConfig>
										  </paymentPageData>
										  ' : '')
										  .'
								 </doDeal>
							</request>
						   </ashrait>'
		);
	}
	
	protected function getInstallmentXmlStructure($credentials, $xmlParams, $installmentParams, $addonData) {
		$ZParameter = !empty($addonData['aid']) ? '<addonData>' . $addonData['aid']  . '</addonData>' : '';
		$ppsConfig  = $this->getPPSConfigJSON($xmlParams);
		return array(
			'user' => $credentials['user'],
			'password' => $credentials['password'],
			/* Build Ashrait XML to post */
			'int_in' => '<ashrait>                                      
							<request>
								 <version>' . $xmlParams['version'] . '</version>
								 <language>' . $xmlParams['language'] . '</language>
								 <dateTime/>
								 <command>doDeal</command>
								 <doDeal>
										  <successUrl>' . $xmlParams['ok_page'] . '</successUrl>
										  ' . $xmlParams['addFailPage'] . '
										  <terminalNumber>' . $credentials['redirect_terminal'] . '</terminalNumber>
										  <mainTerminalNumber/>
										  <cardNo>CGMPI</cardNo>
										  <total>' . $installmentParams['amount'] . '</total>
										  <user>' . $addonData['txid'] . '</user>
									      ' . $ZParameter . '
										  <transactionType>Debit</transactionType>
										  <creditType>Payments</creditType>
										  <currency>ILS</currency>
										  <transactionCode>Phone</transactionCode>
										  <authNumber/>
										  <numberOfPayments>' . $installmentParams['number_of_payments'] . '</numberOfPayments>
										  <firstPayment>' . (!empty($installmentParams['first_payment']) ? $installmentParams['first_payment'] : ''). '</firstPayment>
										  <periodicalPayment>' . (!empty($installmentParams['periodical_payments']) ? $installmentParams['periodical_payments'] : '') . '</periodicalPayment>
										  <validation>TxnSetup</validation>
										  <dealerNumber/>
										  <mid>' . (int) $credentials['mid'] . '</mid>
										  <uniqueid>' . time() . rand(100, 1000) . '</uniqueid>
										  <mpiValidation>' . $xmlParams['mpiValidation'] . '</mpiValidation>
										  <customerData>
										   <userData1>' . $xmlParams['aid'] . '</userData1>
										   <userData2>' . $xmlParams['userData2'] . '</userData2>
										   <userData3/>
										   <userData4/>
										   <userData5/>
										   <userData6/>
										   <userData7/>
										   <userData8/>
										   <userData9/>
										   <userData10/>
										  </customerData>
										  '. (!empty($ppsConfig) ?
										  '<paymentPageData>
											<ppsJSONConfig>
												'.$ppsConfig.'
											</ppsJSONConfig>
										  </paymentPageData>
										  ' : '')
										  .'
								 </doDeal>
							</request>
						   </ashrait>'
		);
	}
	
	protected function addLeadingZero($param) {
		return str_pad($param, 2, "0", STR_PAD_LEFT);
	}

	public function createRecurringBillingProfile($aid, $gatewayDetails, $params = []) {
		return false;
	}
	
	protected function getCardExpiration($old_card_expiration){
		$cgConfig = Billrun_Factory::config()->getConfigValue('creditguard');
		$years = $cgConfig['years_to_extend_card_expiration'];
		return substr($old_card_expiration, 0, 2) . ((substr($old_card_expiration, 2, 4) + $years) % 100);
	}
	
	protected function updateAccountCardExpiration($paymentParams, $gatewayDetails){
		$this->account->loadAccountForQuery(array('aid' => $paymentParams['aid']));
		$accountGateway = $this->account->payment_gateway;
		$accountGateway['active']['card_expiration'] = $gatewayDetails['card_expiration'];
		if (isset($accountGateway['active']['generate_token_time']->sec)) {
			$accountGateway['active']['generate_token_time'] = date("Y-m-d H:i:s", $accountGateway['active']['generate_token_time']->sec);
		}
		$time = date(Billrun_Base::base_datetimeformat);
		$query = array(
			'aid' => $paymentParams['aid'],
			'type' => 'account',
			'effective_date' => $time,
		);
		$update = array(
			'from' => $time,
			'payment_gateway' => $accountGateway,
		);
		Billrun_Factory::log("Updating expiration date for aid=" . $paymentParams['aid'] . " to date " . $gatewayDetails['card_expiration'], Zend_Log::DEBUG);
		try {
			$this->account->permanentChange($query, $update);
			Billrun_Factory::log("Expiration date was updated for aid=" . $paymentParams['aid'] . " to " . $gatewayDetails['card_expiration'], Zend_Log::DEBUG);
		} catch (Exception $ex) {
			Billrun_Factory::log("Expiration date " . $gatewayDetails['card_expiration'] . " was failed to update for aid=" . $paymentParams['aid'], Zend_Log::ALERT);
			return false;
		}
		return true;
	}
	
	public function addAdditionalParameters($request) {
		$keepCCDetails = $request->get('keepCCDetails');
		if ($keepCCDetails == 'true') {
			return array('keepCCDetails' => true);
		}
		return array();
	}

	protected function getPPSConfigJSON($params = array()) {
		$customParams = $this->getGatewayCustomParams();
		$basicParams = $this->getGatewayCredentials();
		if(empty($customParams['paymentPageData']['ppsJSONConfig'])) {
			if (isset($params['tokenize_option'])) {
				$ppsConfig = array(
					'uiCustomData' => array(
						'keepCCDetails' => !empty($params['tokenize_option']),
					),
				);
			} else {
				return null;
			}
		} else {
			$ppsConfig = $customParams['paymentPageData']['ppsJSONConfig'];

			if(!empty($basicParams['ancestor_urls']) && trim($basicParams['ancestor_urls'])) {
				$ppsConfig['frameAncestorURLs'] = $basicParams['ancestor_urls'];
			}

			if ($params['transactionType'] == 'RecurringDebit') {
				$customStyleParamName = 'custom_style';
				$customTextParamName = 'custom_text';
			} else {
				$customStyleParamName = 'custom_style_singlepayment';
				$customTextParamName = 'custom_text_singlepayment';
			}
			
			if (!empty($basicParams[$customStyleParamName]) && trim($basicParams[$customStyleParamName])) {
				$ppsConfig['uiCustomData']['customStyle'] = $basicParams[$customStyleParamName];
			}

			if (!empty($basicParams[$customTextParamName])) {
				$custom_text_parsed = json_decode($basicParams[$customTextParamName]);
				if ($custom_text_parsed) {
					$ppsConfig['uiCustomData']['customText'] = $custom_text_parsed;
				} else {
					Billrun_Factory::log('Billrun_PaymentGateway_CreditGuard::getPPSConfigJSON -  customText json cannot  be parsed  correctly',Zend_Log::WARN);
				}
			}

			if ($params['tokenize_option']) {
				$ppsConfig['uiCustomData']['keepCCDetails'] = !empty($params['tokenize_option']);
			}
		}

		 return json_encode($ppsConfig,JSON_PRETTY_PRINT| JSON_UNESCAPED_LINE_TERMINATORS | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

	}
	
	public function extendCardExpiration($paymentParams, $gatewayDetails){
		$old_card_expiration = $gatewayDetails['card_expiration'];
		$gatewayDetails['card_expiration'] = $this->getCardExpiration($old_card_expiration);
		if ($this->updateAccountCardExpiration($paymentParams, $gatewayDetails)){
			return $gatewayDetails['card_expiration'];
		}
		return $old_card_expiration;
	}
	
	public function getSecretFields() {
		return array('password');
	}

	/**
	 * mirror function for sendJ5Request
	 * @deprecated since version 5.16
	 */
	public function sendRecurringMigrationRequest($aid, $gatewayDetails){
		return $this->sendJ5Request($aid, $gatewayDetails, 'RecurringMigration');
	}

	/**
	 * method to send J5 request to CG gw
	 * 
	 * @param int $aid the account id
	 * @param array $gatewayDetails the gateway details
	 * @param string $transactionType the transcation type RecurringDebit or RecurringMigration
	 * @param string $terminal the terminal to request from redirect_terminal or charging_terminal
	 * 
	 * @return the response from CG gw
	 */
	public function sendJ5Request($aid, $gatewayDetails, $transactionType = 'RecurringDebit', $terminal = 'redirect_terminal'){
		$credentials = $this->getGatewayCredentials();
		$xmlParams['version'] = $credentials['version'] ?? '2000';
		$postArray = $this->getJ5Xml($credentials, $xmlParams, $gatewayDetails, $transactionType, $terminal);
		$postString = http_build_query($postArray);
		if (function_exists("curl_init")) {
			Billrun_Factory::log("Requesting token from " . $this->billrunName . " for account " . $aid, Zend_Log::INFO);
			Billrun_Factory::log("Payment gateway token request: " . print_R($postArray, 1), Zend_Log::DEBUG);
			$response = Billrun_Util::sendRequest($this->EndpointUrl, $postString, Zend_Http_Client::POST, array('Accept-encoding' => 'deflate'), null, 0);
			Billrun_Factory::log("Payment gateway token response: " . print_R($response, 1), Zend_Log::DEBUG);
		}
		return $response;
	}


	protected function getJ5Xml($credentials, $xmlParams, $gatewayDetails, $transactionType, $terminal = 'redirect_terminal') {
		if ($transactionType == 'RecurringMigration') {
			$auth_number = '<authNumber>' . $gatewayDetails['auth_number'] . '</authNumber>';
		} else {
			$auth_number = '';
		}
		return array(
			'user' => $credentials['user'],
			'password' => $credentials['password'],
			/* Build Ashrait XML to post */
			'int_in' => '<ashrait>                                      
							<request>
								 <version>' . ($xmlParams['version'] ?? '2000') . '</version>
								 <dateTime>' . ($xmlParams['date'] ?? date('Y-m-d H:i:s')) . '</dateTime>
								 <command>doDeal</command>
								 <requestId></requestId>
								 <doDeal>
										  <terminalNumber>' . $credentials[$terminal] . '</terminalNumber>
										  <validation>verify</validation>
										  <total>100</total>
										  <groupId></groupId>
										  <currency>ILS</currency>
										  <creditType>RegularCredit</creditType>
										  <transactionCode>Phone</transactionCode>
										  <transactionType>' . ($transactionType ?? 'RecurringDebit') . '</transactionType>
										  <user></user>
										  <externalId></externalId>
										  <cardExpiration>' . $gatewayDetails['card_expiration'] . '</cardExpiration>
										  <cardNo></cardNo>
										  <cgUid></cgUid>
										  <cardId>' . $gatewayDetails['card_token'] . '</cardId>
										  ' . $auth_number . '
										  <ashraitEmvData>
										 		 <recurringTotalNo>999</recurringTotalNo>
										 		 <recurringTotalSum></recurringTotalSum>
										 		 <recurringFrequency>04</recurringFrequency>
										  </ashraitEmvData>
										  <updateGroupId></updateGroupId>
								 </doDeal>
							</request>
						 </ashrait>'
		);
	}
	
	/**
	 * refund historic transaction by transaction id
	 * 
	 * @param string $txId the transaction id
	 * 
	 * @return mixed the refund transaction details on success, false on failure
	 */
	public function refundTransactionByTxId($txId) {
		$transaction = $this->fetchTransactionById($txId);
		return $transaction && $this->refundTransaction($transaction);
	}
	
	/**
	 * fetch transaction from bills by the CG transaction id
	 * 
	 * @param string $txId the transaction id
	 * 
	 * @return mixed the bill record if found, else false
	 */
	public function fetchTransactionById($txId) {
		$coll = Billrun_Factory::db()->billsCollection();
		$query = array(
			'payment_gateway.transactionId' => $txId, // required index
		);
		$transaction = $coll->query($query)->cursor()->current();
		if (empty($transaction) || $transaction->isEmpty()) {
			return false;
		}
		return $transaction;
	}
	
	/**
	 * refund historic transaction
	 * 
	 * @param array $transaction the transaction from bill collection
	 * 
	 * @return mixed the refund transaction details on success, false on failure
	 * @throws Exception
	 */
	public function refundTransaction($transaction) {
		$tranId = $transaction['payment_gateway']['transactionId'];
		if (!isset($transaction['gateway_details']['terminal_number'])) {
			$transactionCGDetails = $this->queryTransaction($tranId, $transaction);
			$terminal = $transactionCGDetails['terminalNumber'];
		} else {
			$terminal = $transaction['gateway_details']['terminal_number'];
		}
		$xml = '<ashrait>
			<request>
				<command>refundDeal</command>
				<requesteId>' . time() . '</requesteId>
				<dateTime>' . date('Y-m-d H:i:s') . '</dateTime>
				<version>2000</version>
				<language>HEB</language>
				<refundDeal>
					<terminalNumber>' . $terminal . '</terminalNumber>
					<tranId>' . $transaction['payment_gateway']['transactionId'] . '</tranId>
					<cardNo>' . $transaction['gateway_details']['card_token'] . '</cardNo>
					<total>' . $this->convertAmountToSend($transaction['gateway_details']['transferred_amount']) . '</total>
					<authNumber>' . $transaction['gateway_details']['auth_number'] . '</authNumber>
					<firstPayment>' . ($transaction['installments']['first_payment'] ? $this->convertAmountToSend($transaction['installments']['first_payment']) : '') . '</firstPayment>
					<periodicalPayment>' . ($transaction['installments']['periodical_payment'] ? $this->convertAmountToSend($transaction['installments']['periodical_payment']) : '') . '</periodicalPayment>
					<numberOfPayments>' . ($transaction['installments']['number_of_payments'] ?? '') . '</numberOfPayments>
					<shiftId1></shiftId1>
					<shiftId2></shiftId2>
					<shiftId3></shiftId3>
					<shiftTxnDate></shiftTxnDate>
				</refundDeal>
			</request>
		</ashrait>';
		$params = $this->getGatewayCredentials();
		$req = array(
			'user' => $params['user'],
			'password' => $params['password'],
			'int_in' => $xml
		);
		Billrun_Factory::log('CreditGuard send refund request: ' . print_R($req, 1));
		$res = Billrun_Util::sendRequest($this->EndpointUrl, http_build_query($req), Zend_Http_Client::POST, array('Accept-encoding' => 'deflate'), null, 0);
		Billrun_Factory::log('CreditGuard send refund response: ' . print_R($res, 1));
		if (($params = $this->getResponseDetails($res)) === FALSE) {
			Billrun_Factory::log("Error: Redirecting to " . $this->returnUrlOnError, Zend_Log::ALERT);
			throw new Exception('Operation Failed. Try Again...');
		}
		// add refund to bills
		$this->paySinglePayment($params);
		return $params;
	}
	
	protected function buildInquireTransactionQuery($params, $terminal = 'redirect_terminal') {
		$credentials = $this->getGatewayCredentials();
		$terminalNumber = $credentials[$terminal] ?? $params['redirect_terminal'];
		$version = $params['version'] ?? '2000';
		return array(
			'user' => $credentials['user'],
			'password' => $credentials['password'],
			/* Build Ashrait XML to post */
			'int_in' => '<ashrait>
							<request>
							 <language>HEB</language>
							 <requestId>' . time() . '</requestId>
							 <version>' . $version . '</version>
							 <command>inquireTransactions</command>
							 <inquireTransactions>
							    <tranId>' . $params['txId'] . '</tranId>
							 </inquireTransactions>
							</request>
					   </ashrait>'
		);
	}
	

	/**
	 * method to query CG for transaction by transaction id
	 * 
	 * @param string $txId transaction id 
	 * @param array $params transaction parameters
	 * 
	 * @return boolean
	 */
	public function queryTransaction($txId, $params = []) {
		if (!isset($params['terminal'])) {
			$params['terminal'] = 'redirect_terminal';
		}
		$params['txId'] = $txId;
		$postArray = $this->buildInquireTransactionQuery($params, $params['terminal']);
		if ($this->isNeedAdjustingRequest()){
			$postString = http_build_query($postArray);
		} else {
			$postString = $postArray;
		}
		$result = Billrun_Util::sendRequest($this->EndpointUrl, $postString, Zend_Http_Client::POST, array('Accept-encoding' => 'deflate'), null, 0);
		if (empty($result)) {
			return false;
		}

		Billrun_Factory::log('CG query transaction found: ' . $result);
		$xmlObj = simplexml_load_string($this->convertXml($result));
		$retObj = json_decode(json_encode($xmlObj->response->inquireTransactions->transactions->transaction), TRUE);
		return $retObj;
	}
	
	protected function convertXml($xml) {
		if (strpos(strtoupper($xml), 'HEB')) {
			return iconv("utf-8", "iso-8859-8", $xml);
		}
		return $xml;
	}
}
