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
	
	protected $redirectUrl;
	protected $successUrl;
	protected $failUrl;
	
	
	
	public function getSessionTransactionId() {
		
	}
	
	
	public function ValidateData(){
		
	}
	
	
	public function charge(){
		
	}
	
	
	
	
	
	protected function getToken($aid, $returnUrl) {
		$this->cgConf['tid'] = Billrun_Factory::config()->getConfigValue('CG.conf.tid');
		$this->cgConf['mid'] = (int)Billrun_Factory::config()->getConfigValue('CG.conf.mid');
		$this->cgConf['amount'] = (int)Billrun_Factory::config()->getConfigValue('CG.conf.amount');
		$this->cgConf['user'] = Billrun_Factory::config()->getConfigValue('CG.conf.user');
		$this->cgConf['password'] = Billrun_Factory::config()->getConfigValue('CG.conf.password');
		$this->cgConf['cg_gateway_url'] = Billrun_Factory::config()->getConfigValue('CG.conf.gateway_url');
		$this->cgConf['aid'] = $aid;
		$this->cgConf['ok_page'] = $this->getOkPage();
		$this->cgConf['return_url'] = $returnUrl;
		$this->cgConf['language'] = "ENG";
		
		$post_array = array(
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
		
		$poststring = http_build_query($post_array);
		
		
		//init curl connection
		if (function_exists("curl_init")) {
			$result= Billrun_Util::sendRequest($this->cgConf['cg_gateway_url'], $poststring, Zend_Http_Client::POST, array('Accept-encoding' => 'deflate'), null, 0);
		}

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
	
	
		
 	public function getOkPage() {
		$okTemplate = Billrun_Factory::config()->getConfigValue('CG.conf.ok_page');
		$request = $this->getRequest();
		$pageRoot = $request->getServer()['HTTP_HOST'];
		$protocol = empty($request->getServer()['HTTPS'])? 'http' : 'https';
		$okPageUrl = sprintf($okTemplate, $protocol, $pageRoot);
		return $okPageUrl;
	}
	

}
