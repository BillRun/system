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
 * @since    5.0
 * 
 */

class OkPageAction extends ApiAction {

	protected $card_token;
	protected $card_expiration;
	protected $subscribers;
	protected $aid;
	protected $personal_id;

	public function execute() {
		$request = $this->getRequest();
		$this->subscribers = Billrun_Factory::db()->subscribersCollection();
		$transaction_id = $request->get("txId");
		if (is_null($transaction_id)) {
			return $this->setError("Operation Failed. Try Again...", $request);
		}
		
		$cursor = $this->subscribers->query(array('CG_transaction_id' => $transaction_id))->cursor();
		if (count($cursor) === 0){
			return $this->setError("Wrong Transaction ID", $request);
		}
		if ($this->getTransactionDetails($transaction_id) === FALSE){
			return $this->setError("Operation Failed. Try Again...", $request);
		}
		
		// Validate the process.
		if(!$this->validateCreditGuardProcess()) {
			return $this->setError("Operation Failed. Try Again...", $request);			
		}
	}

	/**
	 * Check that the process that has now ended, actually started, and not too long ago.
	 * @return boolean
	 */
	protected function validateCreditGuardProcess() {
		// TODO: Maybe we should use $this->cache but I can't understand it
		$cache = Billrun_Factory::cache();
		if(empty($cache)) {
			// TODO: This can be a security breach
			return true;
		}
		
		// Get is started
		$isStartedKey = "credit_in_process." . $this->aid;
		$startedIndication = $cache->get($isStartedKey);
		if(empty($startedIndication)) {
			return false;
		}
		
		// Remove the value from the cache
		$cache->remove($isStartedKey);
		
		list($started, $when) = each($startedIndication);
		
		if($started !== true) {
			// TODO: If we put too many different error message, it can leak information
			// about how are code looks
			return false;
		}
		
		// Check how long has passed.
		$timePassed = time() - $when;
		
		// Three minutes
		// TODO: What value should we put here?
		if($timePassed > 180) {
			return false;
		}
		
		return true;
	}
	
	public function getTransactionDetails($txId) {

		$cgConf['tid'] = Billrun_Factory::config()->getConfigValue('CG.conf.tid');
		$cgConf['mid'] = (int)Billrun_Factory::config()->getConfigValue('CG.conf.mid');
		$cgConf['txId'] = $txId;
		$cgConf['user'] = Billrun_Factory::config()->getConfigValue('CG.conf.user');
		$cgConf['password'] = Billrun_Factory::config()->getConfigValue('CG.conf.password');
		$cgConf['cg_gateway_url'] = Billrun_Factory::config()->getConfigValue('CG.conf.gateway_url');

		$post_array = array(
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
		
		$poststring = http_build_query($post_array);

			
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
			return true;
		} else {
			die("simplexml_load_string function is not support, upgrade PHP version!");
		}
	}

}
