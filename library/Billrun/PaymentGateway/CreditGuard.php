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

	protected $cgConf;
	protected $EndpointUrl;
	protected $billrunName = "CreditGuard";

	protected function __construct() {
		if (Billrun_Factory::config()->isProd()) {
			// TODO: define 'live' url for payment gateway.
		} else {  // test/dev environment
			$this->EndpointUrl = "https://kupot1t.creditguard.co.il/xpo/Relay";
		}
	}

	public function updateSessionTransactionId() {
		$url_array = parse_url($this->redirectUrl);
		$str_response = array();
		parse_str($url_array['query'], $str_response);
		$this->transactionId = $str_response['txId'];
	}

	protected function buildPostArray($aid, $returnUrl, $okPage) {
		$this->cgConf['tid'] = Billrun_Factory::config()->getConfigValue('CG.conf.tid');
		$this->cgConf['mid'] = (int) Billrun_Factory::config()->getConfigValue('CG.conf.mid');
		$this->cgConf['amount'] = (int) Billrun_Factory::config()->getConfigValue('CG.conf.amount');
		$this->cgConf['user'] = Billrun_Factory::config()->getConfigValue('CG.conf.user');
		$this->cgConf['password'] = Billrun_Factory::config()->getConfigValue('CG.conf.password');
		$this->cgConf['cg_gateway_url'] = Billrun_Factory::config()->getConfigValue('CG.conf.gateway_url');
		$this->cgConf['aid'] = $aid;
		$this->cgConf['ok_page'] = $okPage;
		$this->cgConf['return_url'] = $returnUrl;
		$this->cgConf['language'] = "ENG";

		return $post_array = array(
			'user' => $this->cgConf['user'],
			'password' => $this->cgConf['password'],
			/* Build Ashrait XML to post */
			'int_in' => '<ashrait>                                      
							<request>
								 <version>1000</version>
								 <language>' . $this->cgConf['language'] . '</language>
								 <dateTime></dateTime>
								 <command>doDeal</command>
								 <doDeal>
										 <successUrl>' . $this->cgConf['ok_page'] . '</successUrl>
										  <terminalNumber>' . $this->cgConf['tid'] . '</terminalNumber>
										  <mainTerminalNumber/>
										  <cardNo>CGMPI</cardNo>
										  <total>' . $this->cgConf['amount'] . '</total>
										  <transactionType>Debit</transactionType>
										  <creditType>RegularCredit</creditType>
										  <currency>ILS</currency>
										  <transactionCode>Phone</transactionCode>
										  <authNumber/>
										  <numberOfPayments/>
										  <firstPayment/>
										  <periodicalPayment/>
										  <validation>TxnSetup</validation>
										  <dealerNumber/>
										  <user>something</user>
										  <mid>' . $this->cgConf['mid'] . '</mid>
										  <uniqueid>' . time() . rand(100, 1000) . '</uniqueid>
										  <mpiValidation>Normal</mpiValidation>
										  <email>someone@creditguard.co.il</email>
										  <clientIP/>
										  <customerData>
										   <userData1>' . $this->cgConf['aid'] . '</userData1>
										   <userData2>' . $this->cgConf['return_url'] . '</userData2>
										   <userData3/>
										   <userData4/>
										   <userData5/>
										   <userData6/>
										   <userData7/>
										   <userData8/>
										   <userData9/>
										   <userData10/>
										  </customerData>
								 </doDeal>
							</request>
						   </ashrait>'
		);
	}

	protected function updateRedirectUrl($result) {
		if (function_exists("simplexml_load_string")) {
			if (strpos(strtoupper($result), 'HEB')) {
				$result = iconv("utf-8", "iso-8859-8", $result);
			}
			$xmlObj = simplexml_load_string($result);

			if (isset($xmlObj->response->doDeal->mpiHostedPageUrl)) {

				$this->redirectUrl = $xmlObj->response->doDeal->mpiHostedPageUrl;
			} else {
				die('<strong>Can\'t Create Transaction</strong> <br />' .
					'Error Code: ' . $xmlObj->response->result . '<br />' .
					'Message: ' . $xmlObj->response->message . '<br />' .
					'Addition Info: ' . $xmlObj->response->additionalInfo);
			}
		} else {
			die("simplexml_load_string function is not support, upgrade PHP version!");
		}
	}

	protected function buildTransactionPost($txId) {
		$cgConf['tid'] = Billrun_Factory::config()->getConfigValue('CG.conf.tid');
		$cgConf['mid'] = (int) Billrun_Factory::config()->getConfigValue('CG.conf.mid');
		$cgConf['txId'] = $txId;
		$cgConf['user'] = Billrun_Factory::config()->getConfigValue('CG.conf.user');
		$cgConf['password'] = Billrun_Factory::config()->getConfigValue('CG.conf.password');
		$cgConf['cg_gateway_url'] = Billrun_Factory::config()->getConfigValue('CG.conf.gateway_url');

		return $post_array = array(
			'user' => $cgConf['user'],
			'password' => $cgConf['password'],
			/* Build Ashrait XML to post */
			'int_in' => '<ashrait>
							<request>
							 <language>HEB</language>
							 <command>inquireTransactions</command>
							 <inquireTransactions>
							  <terminalNumber>' . $cgConf['tid'] . '</terminalNumber>
							  <mainTerminalNumber/>
							  <queryName>mpiTransaction</queryName>
							  <mid>' . $cgConf['mid'] . '</mid>
							  <mpiTransactionId>' . $cgConf['txId'] . '</mpiTransactionId>
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
			if (!isset($xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->result))
				return false;
			echo "<br /> THE TRANSACTION WAS A SUCCESS ";   // TODO: remove after tests

			$this->saveDetails['card_token'] = (string) $xmlObj->response->inquireTransactions->row->cardId;
			$this->saveDetails['card_expiration'] = (string) $xmlObj->response->inquireTransactions->row->cardExpiration;
			$this->saveDetails['aid'] = (int) $xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->doDeal->customerData->userData1;
			$this->saveDetails['return_url'] = (string) $xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->doDeal->customerData->userData2;
			$this->saveDetails['personal_id'] = (string) $xmlObj->response->inquireTransactions->row->personalId;

			return true;
		} else {
			die("simplexml_load_string function is not support, upgrade PHP version!");
		}
	}

	protected function buildSetQuery() {
		return array(
			'payment_gateway' => array(
				'name' => $this->billrunName,
				'card_token' => (string) $this->saveDetails['card_token'],
				'card_expiration' => (string) $this->saveDetails['card_expiration'],
				'personal_id' => (string) $this->saveDetails['personal_id'],
				'transaction_exhausted' => true
			)
		);
	}

	protected function setConnectionParameters($params) {
		
	}

	public function getDefaultParameters() {
		return array("user" => "", "password" => "", 'terminal_id' => "", 'mid' => "");
	}

	public function authenticateCredentials($params) {
		$cgConf['tid'] = $params['terminal_id'];
		$cgConf['mid'] = $params['mid'];
		$cgConf['user'] = $params['user'];
		$cgConf['password'] = $params['password'];

		$authArray = array(
			'user' => $cgConf['user'],
			'password' => $cgConf['password'],
			/* Build Ashrait XML to post */
			'int_in' => '<ashrait>
							<request>
							 <language>HEB</language>
							 <command>inquireTransactions</command>
							 <inquireTransactions>
							  <terminalNumber>' . $cgConf['tid'] . '</terminalNumber>
							  <mainTerminalNumber/>
							  <queryName>mpiTransaction</queryName>
							  <mid>' . $cgConf['mid'] . '</mid>
							  <mpiTransactionId>' . 1 . '</mpiTransactionId>
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

		$authString = http_build_query($authArray);
		if (function_exists("curl_init")) {
			$result = Billrun_Util::sendRequest($this->EndpointUrl, $authString, Zend_Http_Client::POST, array('Accept-encoding' => 'deflate'), null, 0);
		}
		if (strpos(strtoupper($result), 'HEB')) {
			$result = iconv("utf-8", "iso-8859-8", $result);
		}
		$xmlObj = simplexml_load_string($result);
		$codeResult = (string) $xmlObj->response->result;
		if ($codeResult == "405") {
			return false;
		} else {
			return true;
		}
	}

	protected function pay($gatewayDetails) {
		$paymentArray = $this->buildPaymentRequset($gatewayDetails);
		$paymentString = http_build_query($paymentArray);
		if (function_exists("curl_init")) {
			$result = Billrun_Util::sendRequest($this->EndpointUrl, $paymentString, Zend_Http_Client::POST, array('Accept-encoding' => 'deflate'), null, 0);
		}
		if (strpos(strtoupper($result), 'HEB')) {
			$result = iconv("utf-8", "iso-8859-8", $result);
		}
		$xmlObj = simplexml_load_string($result);
		$codeResult = (string) $xmlObj->response->result;
		return $codeResult;
	}

	protected function buildPaymentRequset($gatewayDetails) {
		$this->cgConf['tid'] = Billrun_Factory::config()->getConfigValue('CG.conf.tid');
		$this->cgConf['mid'] = (int) Billrun_Factory::config()->getConfigValue('CG.conf.mid');
		$this->cgConf['amount'] = (int) $gatewayDetails['amount'];
		$this->cgConf['user'] = Billrun_Factory::config()->getConfigValue('CG.conf.user');
		$this->cgConf['password'] = Billrun_Factory::config()->getConfigValue('CG.conf.password');
		$this->cgConf['cg_gateway_url'] = Billrun_Factory::config()->getConfigValue('CG.conf.gateway_url');
		$this->cgConf['language'] = "ENG";

		return $post_array = array(
			'user' => $this->cgConf['user'],
			'password' => $this->cgConf['password'],
			/* Build Ashrait XML to post */
			'int_in' => '<ashrait>
								<request>
								<command>doDeal</command>
								<requestId>23468</requestId>
								<version>1001</version>
								<language>Eng</language>
								<mayBeDuplicate>0</mayBeDuplicate>
									<doDeal>
										<terminalNumber>' . $this->cgConf['tid'] . '</terminalNumber>
										<cardId>' . $gatewayDetails['card_token'] . '</cardId>
										<cardExpiration>' . $gatewayDetails['card_expiration'] . '</cardExpiration>
										<creditType>RegularCredit</creditType>
										<currency>Usd</currency>
										<transactionCode>Phone</transactionCode>
										<transactionType>Debit</transactionType>
										<total>' . $gatewayDetails['amount'] . '</total>
										<validation>AutoComm</validation>
									</doDeal>
								</request>
						</ashrait>'
		);
	}

	protected function isCompleted($status) {
		if ($status == "000") {
			return true;
		}
		return false;
	}

	protected function isPending($status) {
		return false;
	}

	protected function isRejected($status) {
		if ($status != "000") {
			return true;
		}
		return false;
	}

	public function verifyPending($txId) {
		
	}

	public function hasPendingStatus() {
		return false;
	}

}
