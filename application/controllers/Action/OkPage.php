<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * OkPage action class
 *
 * @package  Action
 */

class OkPageAction extends ApiAction {

	protected $card_token;
	protected $card_expiration;
	protected $subscribers;
	protected $aid;
	protected $personal_id;
	protected $CG_transaction_id;

	public function execute() {
		$request = $this->getRequest();
		$transaction_id = $request->get("txId");
		if (is_null($transaction_id)) {
			return $this->setError("Operation Failed. Try Again...", $request);
		} else {
			$this->getTransactionDetails($transaction_id);
		}

		$today = new MongoDate();
		$this->subscribers = Billrun_Factory::db()->subscribersCollection();
		$this->subscribers->update(array('aid' => (int) $this->aid, 'from' => array('$lte' => $today), 'to' => array('$gte' => $today)), array('$set' => array('card_token' => (string) $this->card_token, 'card_expiration' => (string) $this->card_expiration, 'personal_id' => (string) $this->personal_id, 'CG_transaction_id' => (string) $this->CG_transaction_id)), array("multiple" => true));
	}

	public function getTransactionDetails($txId) {

		$cgConf['tid'] = '0962832';
		$cgConf['mid'] = 10912;
		$cgConf['txId'] = $txId;
		$cgConf['user'] = 'SDOC';
		$cgConf['password'] = 'sD#4R!3r';
		$cgConf['cg_gateway_url'] = "https://kupot1t.creditguard.co.il/xpo/Relay";

		$poststring = '';
		$poststring = 'user=' . $cgConf['user'];
		$poststring .= '&password=' . $cgConf['password'];

		/* Build Ashrait XML to post */
		$poststring.='&int_in=<ashrait>
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
                                                   </ashrait>';

		//init curl connection
		if (function_exists("curl_init")) {
			$CR = curl_init();
			curl_setopt($CR, CURLOPT_URL, $cgConf['cg_gateway_url']);
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
			// Example to print out status text
			//print_r($xmlObj);
			if (!isset($xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->result))
				return false;
			echo "<br /> THE TRANSACTION WAS A SUCCESS ";
			
			var_dump($xmlObj->response->inquireTransactions->row);
			$this->card_token = $xmlObj->response->inquireTransactions->row->cardId;
			$this->card_expiration = $xmlObj->response->inquireTransactions->row->cardExpiration;
			$this->aid = $xmlObj->response->inquireTransactions->row->cgGatewayResponseXML->ashrait->response->doDeal->customerData->userData1;
			$this->personal_id = $xmlObj->response->inquireTransactions->row->personalId;
			$this->CG_transaction_id = $xmlObj->response->inquireTransactions->row->mpiTransactionId;
			return true;
		} else {
			die("simplexml_load_string function is not support, upgrade PHP version!");
		}
	}

}
