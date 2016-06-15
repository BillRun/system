<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * CreditGuard action class
 *
 * @package  Action
 */
class CreditGuardAction extends ApiAction {

	protected $cgConf;
	protected $url;

	public function execute() { 
		$request = $this->getRequest();
		$aid = $request->get("aid");
		$hash = $request->get("hash");
		if (is_null($aid) || !is_numeric($aid)) {
			return $this->setError("need to pass numeric aid", $request);
		}

		$calculated_hash = md5($subscriber_id . 'id2016');
		if ($hash == $calculated_hash) {
			$this->getToken($aid);
			$this->forceRedirect($this->url);
		}
	}

	protected function forceRedirect($uri) {
		if (empty($uri)) {
			$uri = '/';
		}
		header('Location: ' . $uri);
		exit();
	}

	public function getToken($aid) {
		$this->cgConf['tid'] = '0962832';
		$this->cgConf['mid'] = 10912;
		$this->cgConf['amount'] = 100;
		$this->cgConf['user'] = 'SDOC';
		$this->cgConf['password'] = 'sD#4R!3r';
		$this->cgConf['cg_gateway_url'] = "https://kupot1t.creditguard.co.il/xpo/Relay";
		$this->cgConf['aid'] = $aid;

		$poststring = 'user=' . $this->cgConf['user'];
		$poststring .= '&password=' . $this->cgConf['password'];

		/* Build Ashrait XML to post */
		$poststring.='&int_in=<ashrait>
                                                   <request>
                                                        <version>1000</version>
                                                        <language>HEB</language>
                                                        <dateTime></dateTime>
                                                        <command>doDeal</command>
                                                        <doDeal>
                                                                <successUrl>http://46.101.149.208/api/Okpage</successUrl>
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
                                                                  <userData2/>
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
                                                  </ashrait>';

		//init curl connection
		if (function_exists("curl_init")) {

			$CR = curl_init();
			curl_setopt($CR, CURLOPT_URL, $this->cgConf['cg_gateway_url']);
			curl_setopt($CR, CURLOPT_POST, 1);
			curl_setopt($CR, CURLOPT_FAILONERROR, true);
			curl_setopt($CR, CURLOPT_POSTFIELDS, $poststring);
			curl_setopt($CR, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($CR, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($CR, CURLOPT_FAILONERROR, true);


			//actual curl execution perfom
			$result = curl_exec($CR);
			$error = curl_error($CR);
			// on error - die with error message
			if (!empty($error)) {
				die($error);
			}

			curl_close($CR);
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
