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

	/**
	 * Name of the payment gateway in Omnipay.
	 * @var string
	 */
	protected $omnipayName;
	
	/**
	 * Omnipay object for payment gateway.
	 * @var omnipay
	 */
	protected $omnipayGateway;
	
	protected static $paymentGateways;
	
	/**
	 * url to redirect after getting billing agreement from the payment gateway.
	 * @var string
	 */
	protected $redirectUrl;
	
	/**
	 * endpoint of the payment gateway.
	 * @var string
	 */
	protected $EndpointUrl;
	
	/**
	 * details from the payment gateway about the user.
	 * @var array
	 */
	protected $saveDetails;
	
	/**
	 * billrun class name for the payment gateway.
	 * @var string
	 */
	protected $billrunName;
	
	/**
	 * identifier for the transaction. 
	 * @var string
	 */
	protected $transactionId;
	

	protected $subscribers;
	
	/**
	 * whrere to redirect the use after success.
	 * @var string
	 */
	protected $returnUrl;
	
	/**
	 * regex for rejection status for the payment gateway.
	 * @var string
	 */
	protected $rejectionCodes;
	
	/**
	 * regex for pending status for the payment gateway.
	 * @var string
	 */
	protected $pendingCodes;
	
	/**
	 * regex for completion status for the payment gateway.
	 * @var string
	 */
	protected $completionCodes;

	private function __construct() {

		if ($this->supportsOmnipay()) {
			$this->omnipayGateway = Omnipay\Omnipay::create($this->getOmnipayName());
		}

		if (empty($this->returnUrl)) {
			$this->returnUrl = Billrun_Factory::config()->getConfigValue('billrun.return_url');
		}
	}


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
	 * @param Array $accountQuery - query to get the right account.
	 * @param Int $timestamp - Unix timestamp
	 * @return Int - Account id
	 */
	public function redirectForToken($aid, $accountQuery, $timestamp, $request) {
		$subscribers = Billrun_Factory::db()->subscribersCollection();
		$subscribers->update($accountQuery, array('$set' => array('tennant_return_url' => $accountQuery['tennant_return_url'])));
		$this->getToken($aid, $accountQuery['tennant_return_url'], $request);
		$this->updateSessionTransactionId();

		// Signal starting process.
		$this->signalStartingProcess($aid, $timestamp);
		$this->forceRedirect($this->redirectUrl);
	}

	/**
	 * Updates the current transactionId.
	 * 
	 */
	abstract function updateSessionTransactionId();
	
	/**
	 * Get the Redirect url of the payment gateway.
	 * 
	 * @param $result - response to request to get billing agreement from the payment gateway.
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
	 * @param $result - response from the payment gateway to the request to get billing agreement.
	 */
	abstract protected function getResponseDetails($result);

	/**
	 * Choosing the wanted details from the response to save in the db.
	 * 
	 * @return array - payment gateway object with the wanted details
	 */
	abstract protected function buildSetQuery();


	/**
	 * Checks against the chosen payment gateway if the credentials passed are correct.
	 * 
	 * @param Array $params - details of the payment gateway.
	 * @return Boolean - true if the credentials are correct.
	 */
	abstract public function authenticateCredentials($params);

	/**
	 * Sending request to chosen payment gateway to charge the subscriber according to his bills.
	 * 
	 * @param array $gatewayDetails - Details of the chosen payment gateway
	 * @return String - Status of the payment.
	 */
	abstract protected function pay($gatewayDetails);

	/**
	 * Check the status of previously pending payment.
	 * 
	 * @param string $txId - String that represents the transaction.
	 * @return string - Payment status.
	 */
	abstract public function verifyPending($txId);

	/**
	 * True if the payment gateway can return pending has a status of a payment. 
	 * 
	 */
	abstract public function hasPendingStatus();

	/**
	 * Redirect to the payment gateway page of card details.
	 * 
	 * @param $aid - Account id of the client.
	 * @param $returnUrl - The page to redirect the client after success of the whole process.
	 */
	protected function getToken($aid, $returnUrl, $request) {
		$okTemplate = Billrun_Factory::config()->getConfigValue('PaymentGateways.ok_page');
		$pageRoot = $request->getServer()['HTTP_HOST'];
		$protocol = empty($request->getServer()['HTTPS'])? 'http' : 'https';
		$okPage = sprintf($okTemplate, $protocol, $pageRoot, $this->billrunName);
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
			throw new Exception("Operation Failed. Try Again...");
		}
		if (empty($this->saveDetails['aid'])) {
			$this->saveDetails['aid'] = $this->getAidFromProxy($txId);
		}
		if (!$this->validatePaymentProcess($txId)) {
			throw new Exception("Too much time passed");
		}
		$this->saveAndRedirect();
	}

	protected function saveAndRedirect() {
		$this->subscribers = Billrun_Factory::db()->subscribersCollection();
		$query = Billrun_Utils_Mongo::getDateBoundQuery();
		$query['aid'] = (int) $this->saveDetails['aid'];
		$query['type'] = "account";
		$setQuery = $this->buildSetQuery();       
		$this->subscribers->update($query, array('$set' => $setQuery));

		if (isset($this->saveDetails['return_url'])) {
			$returnUrl = (string) $this->saveDetails['return_url'];
		} else {
			$account = $this->subscribers->query($query)->cursor()->current();
			$returnUrl = $account['tennant_return_url'];
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

	/**
	 * Checking the state of the payment - completed, pending or rejected. 
	 * 
	 * @param paymentGateway $status - status returned from the payment gateway.
	 * @param paymentGateway $gateway - the gateway the client chose to pay through.
	 * @return Array - the status and stage of the payment.
	 */
	public function checkPaymentStatus($status, $gateway) {
		if ($gateway->isCompleted($status)) {
			return array('status' => $status, 'stage' => "Completed");
		} else if ($gateway->isPending($status)) {
			return array('status' => $status, 'stage' => "Pending");
		} else if ($gateway->isRejected($status)) {
			return array('status' => $status, 'stage' => "Rejected");
		} else {
			throw new Exception("Unknown status");
		}
	}
	
	/**
	 * Get the Credentials of the current payment gateway. 
	 * 
	 * @return Array - the status and stage of the payment.
	 */
	protected function getGatewayCredentials() {
		$gateways = Billrun_Factory::config()->getConfigValue('payment_gateways');
		$gatewayName = $this->billrunName;
		$gateway = array_filter($gateways, function($paymentGateway) use ($gatewayName) {
			return $paymentGateway['name'] == $gatewayName;
		});
		$gatewayDetails = current($gateway);
		return $gatewayDetails['params'];
	}
	
	public function getCustomers() {
		$billsColl = Billrun_Factory::db()->billsCollection();
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
				'payment_method' => array(
					'$in' => array('Credit'),
				),
				'suspend_debit' => NULL,
			),
		);
		$res = $billsColl->aggregate($sort, $group, $match);
		return $res;
	}
	
	protected function rearrangeParametres($params){
		foreach ($params as $value) {
			$arranged[$value] = '';
		}
		
		return $arranged;
	}
	
	/**
	 * Checks if the payment is accepted.
	 * 
	 * @param String $status - status of the payment that returned from the payment gateway
	 * @return Boolean - true if the status means completed payment
	 */
	protected function isCompleted($status) {
		return preg_match($this->completionCodes, $status);
	}
	
	/**
	 * Checks if the payment is pending.
	 * 
	 * @param String $status - status of the payment that returned from the payment gateway
	 * @return Boolean - true if the status means pending payment
	 */
	protected function isPending($status) {
		return preg_match($this->pendingCodes, $status);
	}
	
	/**
	 * Checks if the payment is rejected.
	 * 
	 * @param String $status - status of the payment that returned from the payment gateway
	 * @return Boolean - true if the status means rejected payment
	 */
	protected function isRejected($status) {
		return preg_match($this->rejectionCodes, $status);
	}
	
	public function getTransactionId(){
		return $this->transactionId;
	}
 
}
