<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/library/vendor/autoload.php';
require_once APPLICATION_PATH . '/application/controllers/Action/Pay.php';
require_once APPLICATION_PATH . '/application/controllers/Action/Collect.php';

/**
 * This class represents a payment gateway
 *
 * @since    5.2
 */
abstract class Billrun_PaymentGateway {

	use Billrun_Traits_Api_PageRedirect;

	protected $omnipayName;
	protected $omnipayGateway;
	protected static $paymentGateways;
	protected $redirectUrl;
	protected $EndpointUrl;
	protected $saveDetails;
	protected $billrunName;
	protected $transactionId;
	//protected static $paymentMethods = array('Credit');
	protected $subscribers;
	protected $returnUrl;

	private function __construct() {

		if ($this->supportsOmnipay()) {
			$this->omnipayGateway = Omnipay\Omnipay::create($this->getOmnipayName());
		}
		
		if (empty($this->returnUrl)){
			$this->returnUrl = Billrun_Factory::config()->getConfigValue('return_url');
		}
	}

	abstract function updateSessionTransactionId();

	public function __call($name, $arguments) {
		if ($this->supportsOmnipay()) {
			return call_user_func_array(array($this->omnipayGateway, $name), $arguments);
		}
		throw new Exception('Method ' . $name . ' is not supported');
	}

	/**
	 * 
	 * @param string $name the payment gateway name
	 * @return Billrun_PaymentGateway
	 */
	public static function getInstance($name) {
		if (isset(self::$paymentGateways[$name])) {
			$paymentGateway = self::$paymentGateways[$name];
		} else {
			$subClassName = __CLASS__ . '_' . $name;
			if (@class_exists($subClassName)) {
				$paymentGateway = new $subClassName();
				self::$paymentGateways[$name] = $paymentGateway;
			}
		}
		return isset($paymentGateway) ? $paymentGateway : NULL;
	}

	public function supportsOmnipay() {
		return !is_null($this->omnipayName);
	}

	public function getOmnipayName() {
		return $this->omnipayName;
	}

	/**
	 * Redirect to the payment gateway page of card details for getting Billing Agreement id.
	 * 
	 * @param Int $aid - Account id
	 * @param String $returnUrl - The page to redirect the client after success of the whole process.
	 * @param Int $timestamp - Unix timestamp
	 * @return Int - Account id
	 */
	public function redirectForToken($aid, $returnUrl, $timestamp) {
		$this->getToken($aid, $returnUrl);
		$this->updateSessionTransactionId();

		// Signal starting process.
		$this->signalStartingProcess($aid, $timestamp);
		$this->forceRedirect($this->redirectUrl);
	}

	/**
	 * Check if the payment gateway is supported by Billrun.
	 * 
	 * @param $gateway - Payment Gateway object.
	 * @return Boolean
	 */
	public function isSupportedGateway($gateway) {
		$supported = Billrun_Factory::config()->getConfigValue('PaymentGateways.supported');
		return in_array($gateway, $supported);
	}

	/**
	 * Get the Redirect url of the payment gateway.
	 * 
	 * @param $result - response from the payment gateway.
	 */
	abstract protected function updateRedirectUrl($result);

	/**
	 * Build request for start a transaction of getting Billing Agreement id.
	 * 
	 * @param Int $aid - Account id
	 * @param String $returnUrl - The page to redirect the client after success of the whole process.
	 * @param String $okPage - the action to be called after success in filling personal details.
	 * @return array - represents the request
	 */
	abstract protected function buildPostArray($aid, $returnUrl, $okPage);

	/**
	 *  Build request to Query for getting transaction details.
	 * 
	 * @param string $txId - String that represents the transaction.
	 * @return array - represents the request
	 */
	abstract protected function buildTransactionPost($txId);

	/**
	 * Get the name of the parameter that the payment gateway returns to represent billing agreement id.
	 * 
	 * @return String - the name.
	 */
	abstract public function getTransactionIdName();

	/**
	 * Query the response to getting needed details.
	 * 
	 * @param $result - response from the payment gateway.
	 */
	abstract protected function getResponseDetails($result);

	/**
	 * Choosing the wanted details from the response to save in the db.
	 * 
	 * @return array - payment gateway object with the wanted details
	 */
	abstract protected function buildSetQuery();
	
	abstract protected function setConnectionParameters($params);
	
	abstract public function authenticateCredentials($params);
	
	abstract protected function pay($gatewayDetails);


	/**
	 * Redirect to the payment gateway page of card details.
	 * 
	 * @param $aid - Account id of the client.
	 * @param $returnUrl - The page to redirect the client after success of the whole process.
	 */
	protected function getToken($aid, $returnUrl) {
		$okTemplate = Billrun_Factory::config()->getConfigValue('PaymentGateways.ok_page');
		$okPage = sprintf($okTemplate, $this->billrunName);
		$postArray = $this->buildPostArray($aid, $returnUrl, $okPage);
		$postString = http_build_query($postArray);
		if (function_exists("curl_init")) {
			$result = Billrun_Util::sendRequest($this->EndpointUrl, $postString, Zend_Http_Client::POST, array('Accept-encoding' => 'deflate'), null, 0);
		}
		$this->updateRedirectUrl($result);
	}

	/**
	 * Saving Details to Subscribers collection and redirect to our success page or the merchant page if suppiled.
	 * 
	 * @param $txId - String that represents the transaction.
	 */
	public function saveTransactionDetails($txId) {
		$postArray = $this->buildTransactionPost($txId);
		$postString = http_build_query($postArray);
		if (function_exists("curl_init")) {
			$result = Billrun_Util::sendRequest($this->EndpointUrl, $postString, Zend_Http_Client::POST, array('Accept-encoding' => 'deflate'), null, 0);
		}
		if ($this->getResponseDetails($result) === FALSE) {
			return $this->setError("Operation Failed. Try Again...");
		}
		if (empty($this->saveDetails['aid'])) {
			$this->saveDetails['aid'] = $this->getAidFromProxy($txId);
		}
//		if (!$this->validatePaymentProcess($txId)) {
//			return $this->setError("Operation Failed. Try Again...");
//		}
		$this->saveAndRedirect();
	}

	protected function saveAndRedirect() {
		$today = new MongoDate();
		$this->subscribers = Billrun_Factory::db()->subscribersCollection();
		$setQuery = $this->buildSetQuery();
		$this->subscribers->update(array('aid' => (int) $this->saveDetails['aid'], 'from' => array('$lte' => $today), 'to' => array('$gte' => $today), 'type' => "account"), array('$set' => $setQuery));
		
		if (isset($this->saveDetails['return_url'])) {
			$url = (string) $this->saveDetails['return_url'];
		}
		
		if (!empty($url)){
			$returnUrl = $url;
		}
		else if (!empty($this->returnUrl)){
			$returnUrl = $this->returnUrl;
		}	
		else{
			$returnUrl = Billrun_Factory::config()->getConfigValue('PaymentGateways.success_url');
		}
		$this->forceRedirect($returnUrl);
	}

	protected function signalStartingProcess($aid, $timestamp) {
		$paymentColl = Billrun_Factory::db()->creditproxyCollection();
		$query = array("name" => $this->billrunName, "tx" => $this->transactionId, "aid" => $aid);
		$paymentRow = $paymentColl->query($query)->cursor()->current();
		if (!$paymentRow->isEmpty()) {
			if (isset($paymentRow['done'])) {
				return;
			}
			return;
		}

		// Signal start process
		$query['t'] = $timestamp;
		$paymentColl->insert($query);
	}

	/**
	 * Check that the process that has now ended, actually started, and not too long ago.
	 * 
	 * @param string $txId -String that represents the transaction.
	 * @return boolean
	 */
	protected function validatePaymentProcess($txId) {
		$paymentColl = Billrun_Factory::db()->creditproxyCollection();

		// Get is started
		$query = array("name" => $this->billrunName, "tx" => $txId, "aid" => $this->saveDetails['aid']);
		$paymentRow = $paymentColl->query($query)->cursor()->current();
		if ($paymentRow->isEmpty()) {
			// Received message for completed charge, 
			// but no indication for charge start
			return false;
		}

		// Check how long has passed.
		$timePassed = time() - $paymentRow['t'];

		// Three minutes
		// TODO: What value should we put here?
		// TODO: Change to 4 hours, move to conf
		if ($timePassed > 60 * 60 * 4) {
			// Change indication in DB for failure.
			$paymentRow['done'] = false;
		} else {
			// Signal done
			$paymentRow['done'] = true;
		}

		$paymentColl->updateEntity($paymentRow);

		return $paymentRow['done'];
	}

	/**
	 * Get aid from proxy collection if doesn't passed through the payment gateway.
	 * 
	 * @param string $txId -String that represents the transaction.
	 * @return Int - Account id
	 */
	protected function getAidFromProxy($txId) {
		$paymentColl = Billrun_Factory::db()->creditproxyCollection();
		$query = array("name" => $this->billrunName, "tx" => $txId);
		$paymentRow = $paymentColl->query($query)->cursor()->current();
		return $paymentRow['aid'];
	}

	// if omnipay supported need to use this function for making charge, for others like CG need to implement it.
	// TODO: need to check for omnipay supported gateways.
	public function makePayment($stamp) {
		
		$today = new MongoDate();
		$paymentParams = array(
			'dd_stamp' => $stamp
		);
		if (!Billrun_Bill_Payment::removePayments($paymentParams)) { // removePayments if this is a rerun
			throw new Exception('Error removing payments before rerun');
		}
		//$this->customers = iterator_to_array($this->getCustomers());
		$customers = iterator_to_array(self::getCustomers());

//		
//		Billrun_Factory::log()->log('generator entities loaded: ' . count($customers), Zend_Log::INFO);
//		Billrun_Factory::dispatcher()->trigger('afterGeneratorLoadData', array('generator' => $this));
//		$sequence = $this->getSequenceField();
		$involvedAccounts = array();
		$options = array('collect' => FALSE);
		$data = array();
		$subscribers = Billrun_Factory::db()->subscribersCollection();
		$customers_aid = array_map(function($ele) {
			return $ele['aid'];
		}, $customers);

		$subscribers = $subscribers->query(array('aid' => array('$in' => $customers_aid), 'from' => array('$lte' => $today), 'to' => array('$gte' => $today), 'type' => "account"))->cursor();
		foreach ($subscribers as $subscriber) {
			$subscribers_in_array[$subscriber['aid']] = $subscriber;
		}

		foreach ($customers as $customer) {
			$subscriber = $subscribers_in_array[$customer['aid']];
			$involvedAccounts[] = $paymentParams['aid'] = $customer['aid'];
			//$paymentSuccessful = false;
			//$omniRedirect = false;
			$paymentParams['billrun_key'] = $customer['billrun_key'];
			$paymentParams['amount'] = $customer['amount'];
			$paymentParams['source'] = $customer['source'];
			$gatewayDetails = $subscriber['payment_gateway'];
			$gatewayDetails['amount'] = $customer['amount'];
			$gatewayDetails['currency'] = $customer['currency'];
			$gatewayName = $gatewayDetails['name'];
			$gateway = self::getInstance($gatewayName);
			$credentials = $gateway->getConnectionCredentials($gateway);
		//	if ($gatewayName == "PayPal_ExpressCheckout"){
				$gateway->pay($gatewayDetails);  // FOR PAYPAL EXPRESS CHECKOUT
		//	}
			
//			if ($gateway->supportsOmnipay()) {  // TODO: need to implement else for not omnipay supported e.g credit guard.
//				$omniName = $gateway->getOmnipayName();
//				$omniGateway = Omnipay\Omnipay::create($omniName);
//				$omniGateway->setTestMode(true); // TODO: remove when not test mode.
//				$purchaseData = [
//					'amount' => 1.00,
//					'currency' => 'USD',
//					'return_url' => 'http://billrun.com',
//					'cancel_url' => 'http://billrun.com',
//					'token' => $gatewayDetails['card_token'],
//				//	'card' => ['email' => 'foxession-buyer@gmail.com']
//				];
//				$data = array_merge($purchaseData, $credentials);
			//$response = $omniGateway->purchase($data)->send();
			
			
			
				//$a = $response->getRedirectUrl();
			//	$b = $omniGateway->getLogoImageUrl();
//
//				$ref = $response->getTransactionReference();
//				
//				if ($response->isSuccessful()) { // payment is complete
//					$paymentSuccessful = true;
//				} elseif ($response->isRedirect()) {
//					$paymentSuccessful = true;
//					$omniRedirect = true;
//					//$response->redirect(); //  payment is complete + this will automatically forward the customer
//				} 


		//	}

//			if ($paymentSuccessful){
//				$payment = payAction::pay('credit', array($paymentParams), $options)[0];
//			}

//			if ($omniRedirect){
//				$response->redirect();
//			}

			//	$payment = payAction::pay('credit', array($paymentParams), $options)[0];

		}
	}
	protected function getCustomers() {
		$billsColl = Billrun_Factory::db()->billsCollection();
		//$a = self::$paymentMethods;
		$sort = array(
			'$sort' => array(
				'type' => 1,
				'due_date' => -1,
			),
		);
		$group = array(
			'$group' => array(
				'_id' => '$aid',
				'suspend_debit' => array(
					'$first' => '$suspend_debit',
				),
				'type' => array(
					'$first' => '$type',
				),
				'payment_method' => array(
					'$first' => '$payment_method',
				),
				'due' => array(
					'$sum' => '$due',
				),
				'aid' => array(
					'$first' => '$aid',
				),
				'billrun_key' => array(
					'$first' => '$billrun_key',
				),
				'lastname' => array(
					'$first' => '$lastname',
				),
				'firstname' => array(
					'$first' => '$firstname',
				),
				'bill_unit' => array(
					'$first' => '$bill_unit',
				),
				'bank_name' => array(
					'$first' => '$bank_name',
				),
				'due_date' => array(
					'$first' => '$due_date',
				),
				'source' => array(
					'$first' => '$source',
				),
				'amount' => array(
					'$sum' => '$amount',
				),
				'currency' => array(
					'$first' => '$currency',
				),
			),
		);
		$match = array(
			'$match' => array(
				'due' => array(
					'$gt' => Billrun_Bill::precision,
				),
				'aid' => 1234,  // TODO: remove after tests
				'payment_method' => array(
					'$in' => array('Credit'),
				),
				'suspend_debit' => NULL,
			),
		);
		$res = $billsColl->aggregate($sort, $group, $match);
		return $res;
	}
	
	
	/**
	 * Get from config the merchant credentials for connecting to the payment gateway. 
	 * 
	 *@param paymentGateway $gateway - the gateway the client chose to pay through.
	 * @return array - the credentials that are a must for connection.
	 */
	protected function getConnectionCredentials($gateway){
		$paymentGateways = Billrun_Factory::config()->getConfigValue('payment_gateways');
		$gatewayName = $gateway->billrunName;
		$gatewayDetails = array_filter($paymentGateways, function($paymentGateway) use ($gatewayName) {
			return $paymentGateway['name'] == $gatewayName;
			});
		$gatewayCredentials = current($gatewayDetails);
		$requiredCredentials = $gateway->setConnectionParameters($gatewayCredentials['params']);
		return $requiredCredentials;
	}

}
