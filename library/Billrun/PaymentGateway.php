<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/library/vendor/autoload.php';

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

	private function __construct() {

		if ($this->supportsOmnipay()) {
			$this->omnipayGateway = Omnipay\Omnipay::create($this->getOmnipayName());
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
		if (!$this->validatePaymentProcess($txId)) {
			return $this->setError("Operation Failed. Try Again...");
		}

		$today = new MongoDate();
		$this->subscribers = Billrun_Factory::db()->subscribersCollection();
		$setQuery = $this->buildSetQuery();
		$this->subscribers->update(array('aid' => (int) $this->saveDetails['aid'], 'from' => array('$lte' => $today), 'to' => array('$gte' => $today), 'type' => "account"), array('$set' => $setQuery));
		if (isset($this->saveDetails['return_url'])) {
			$returnUrl = (string) $this->saveDetails['return_url'];
			$this->forceRedirect($returnUrl);
		}
		$successUrl = Billrun_Factory::config()->getConfigValue('PaymentGateways.success_url');  // TODO: check	if more correct to define it when we get the request(getRequestAction)
		$this->forceRedirect($successUrl);
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
//	public function makePayment($name, $aid) {
//		if ($this->supportsOmnipay()) {
//
//			$gateway = Omnipay\Omnipay::create($this->omnipayName);
//			$params = $gateway->getDefaultParametes();
//			foreach ($params as $key => $value) {
//				$pam = 5;
//			}
//			$omnipay->setUsername("shani.dalal_api1.billrun.com");
//			$omnipay->setPassword("RRM2W92HC9VTPV3Y");
//			$omnipay->setSignature("AiPC9BjkCyDFQXbSkoZcgqH3hpacA3CKMEmo7jRUKaB3pfQ8x5mChgoR");
//			$omnipay->setTestMode(true); // TODO: change when not test mode.
//		}
////    else {
//
//
//
//		$omnipay->setTestMode(true); // TODO: change when not test mode.
//		$purchaseData = [
//			'testMode' => true,
//			'amount' => 1.00,
//			'currency' => 'USD',
//			'returnUrl' => 'http://www.google.com',
//			'cancelUrl' => 'http://www.ynet.co.il'
//		];
//		$response = $omnipay->purchase($purchaseData)->send();
//		$ref = $response->getTransactionReference();
//		if (!is_null($ref)) { // when there's a Token
//			$response->redirect();
//		} else {
//			//dd("ERROR");   <= This line works but if I put a redirect method as shown below it just shows a blank page. No errors nothing!
//			return redirect(route('payment.error'));
//		}
//		// }
//	}
}
