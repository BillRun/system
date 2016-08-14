<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * CreditGuard action class
 *
 * @package  Action
 * @since    5.0
 * 
 */
class CreditGuardAction extends ApiAction {
	
	protected $cgConf;
	protected $url;
	protected $subscribers;
	protected $CG_transaction_id;

	public function execute() { 
		$request = $this->getRequest();

		// Validate the data.
		$data = $this->validateData($request);
		if($data === null) {
			return $this->setError("Failed to authenticate", $request);
		}
		
		$jsonData = json_decode($data, true);
		if (!isset($jsonData['aid']) || is_null(($aid = $jsonData['aid'])) || !Billrun_Util::IsIntegerValue($aid)) {
			return $this->setError("need to pass numeric aid", $request);
		}
		
		if(!isset($jsonData['t']) || is_null(($timestamp = $jsonData['t']))) {
			return $this->setError("Invalid arguments", $request);			
		}
		
		// TODO: Validate timestamp 't' against the $_SERVER['REQUEST_TIME'], 
		// Validating that not too much time passed.
		
		$this->getToken($aid);
		$url_array = parse_url($this->url);
		$str_response = array();
		parse_str($url_array['query'], $str_response);
		$this->CG_transaction_id = $str_response['txId'];	
		
		// Signal starting process.
		$this->signalStartingProcess($aid, $timestamp);
		
		$this->forceRedirect($this->url);
	}

	protected function signalStartingProcess($aid, $timestamp) {
		$cgColl = Billrun_Factory::db()->creditproxyCollection();
		
		// Get is started
		// TODO: Move to DB
		$query = array("tx" => $this->CG_transaction_id, "aid" => $aid);
		$cgRow = $cgColl->query($query)->cursor()->current();
		
		if(!$cgRow->isEmpty()) {
			if(isset($cgRow['done'])) { 
			   // Blocking relayed message.
			   return;
			}
			
			// TODO: Still blocking? Relaying a mesage after the first did not finish?
			return;
		}
		
		// Signal start process
		$query['t'] = $timestamp;
		$cgColl->insert($query);
	}
	
	protected function forceRedirect($uri) {
		if (empty($uri)) {
			$uri = '/';
		}
		header('Location: ' . $uri);
		exit();
	}
	
	/**
	 * Validates the input data.
	 * @return data - Request data if validated, null if error.
	 */
	public function validateData($request) {
		$data = $request->get("data");
		$signature = $request->get("signature");

		// Get the secret
		$secret = Billrun_Factory::config()->getConfigValue("shared_secret.key");
		if(!$this->validateSecret($secret)) {
			return null;
		}
		
		$hashResult = hash_hmac("sha512", $data, $secret);
		
		// state whether signature is okay or not
		$validData = null;
	
		if(hash_equals($signature, $hashResult)) {
			$validData = $data;
		}
		return $validData;
	}
	
	protected function validateSecret($secret) {
		if(empty($secret) || !is_string($secret)) {
			return false;
		}
		$crc = Billrun_Factory::config()->getConfigValue("shared_secret.crc");
		$calculatedCrc = hash("crc32b", $secret);
		
		// Validate checksum
		return hash_equals($crc, $calculatedCrc);
	}
	
	protected function getOkPage() {
		$okTemplate = Billrun_Factory::config()->getConfigValue('CG.conf.ok_page');
		$request = $this->getRequest();
		$pageRoot = $request->getServer()['HTTP_HOST'];
		$okPageUrl = sprintf($okTemplate, $pageRoot);
		return $okPageUrl;
	}
	
	public function getToken($aid, $return_url) {
		$this->cgConf['tid'] = Billrun_Factory::config()->getConfigValue('CG.conf.tid');
		$this->cgConf['mid'] = (int)Billrun_Factory::config()->getConfigValue('CG.conf.mid');
		$this->cgConf['amount'] = (int)Billrun_Factory::config()->getConfigValue('CG.conf.amount');
		$this->cgConf['user'] = Billrun_Factory::config()->getConfigValue('CG.conf.user');
		$this->cgConf['password'] = Billrun_Factory::config()->getConfigValue('CG.conf.password');
		$this->cgConf['cg_gateway_url'] = Billrun_Factory::config()->getConfigValue('CG.conf.gateway_url');
		$this->cgConf['aid'] = $aid;
		$this->cgConf['ok_page'] = $this->getOkPage();
		$this->cgConf['return_url'] = $return_url;

		
		$post_array = array(
			'user' => $this->cgConf['user'],
			'password' => $this->cgConf['password'],
			 /* Build Ashrait XML to post */
			'int_in' => '<ashrait>                                      
							<request>
								 <version>1000</version>
								 <language>HEB</language>
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

				$this->url = $xmlObj->response->doDeal->mpiHostedPageUrl;
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


}
