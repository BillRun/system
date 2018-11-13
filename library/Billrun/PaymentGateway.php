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
	
	/**
	 * where to redirect the user when unrecoverable error happens.
	 * @var string
	 */
	protected $returnUrlOnError;

	/**
	 * html form for redirection to the payment gateway for filling details.
	 * @var string
	 */
	protected $htmlForm;

	protected function __construct() {

		if ($this->supportsOmnipay()) {
			$this->omnipayGateway = Omnipay\Omnipay::create($this->getOmnipayName());
		}

		if (empty($this->returnUrl)) {
			$this->returnUrl = Billrun_Factory::config()->getConfigValue('billrun.return_url');
		}
		Billrun_Factory::config()->addConfig(APPLICATION_PATH . '/conf/PaymentGateways/' . $this->billrunName . '/' . $this->billrunName .'.ini');
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
	public function redirectToGateway($aid, $accountQuery, $timestamp, $request, $data) {
		$singlePaymentParams = array();
		$options = array();
		$subscribers = Billrun_Factory::db()->subscribersCollection();
		$tenantReturnUrl = $accountQuery['tenant_return_url'];
		unset($accountQuery['tenant_return_url']);
		$subscribers->update($accountQuery, array('$set' => array('tenant_return_url' => $tenantReturnUrl)));
		$this->updateReturnUrlOnEror($tenantReturnUrl);
		$okPage = (isset($data['iframe']) && $data['iframe']) ? $data['ok_page'] : $this->getOkPage($request);
		$failPage = (isset($data['iframe']) && $data['iframe']) ? $data['fail_page'] : false;
		if (isset($data['action']) && $data['action'] == 'single_payment') {
			if (empty($data['amount'])) {
				throw new Exception("Missing amount when making single payment");
			}
			if (isset($data['installments']) && ($data['amount'] != $data['installments']['total_amount'])) {
				throw new Exception("Single payment amount different from installments amount");
			}
			$singlePaymentParams['amount'] = floatval($data['amount']);
		}
		if (isset($data['iframe']) && $data['iframe'] && (is_null($okPage) || is_null($failPage))) {
			throw new Exception("Missing ok/fail pages");
		}
		if (isset($data['installments'])) {
			$options['installments'] = $data['installments'];
		}
		if ($this->needRequestForToken()){
			$response = $this->getToken($aid, $tenantReturnUrl, $okPage, $failPage, $singlePaymentParams, $options);
		} else {
			$updateOkPage = $this->adjustOkPage($okPage);
			$response = $updateOkPage;
		}
		$this->updateRedirectUrl($response);
		$this->updateSessionTransactionId();

		// Signal starting process.
		$this->signalStartingProcess($aid, $timestamp);
		if ($this->isUrlRedirect()){
			Billrun_Factory::log("Redirecting to: " . $this->redirectUrl . " for account " . $aid, Zend_Log::DEBUG);
			if (isset($data['iframe']) && $data['iframe']) {
				return array('content'=> $this->redirectUrl, 'content_type' => 'url');
			}	
			return array('content'=> "Location: " . $this->redirectUrl, 'content_type' => 'url');
		} else if ($this->isHtmlRedirect()){
			Billrun_Factory::log("Redirecting to: " .  $this->billrunName, Zend_Log::DEBUG);
			return array('content'=> $this->htmlForm, 'content_type' => 'html');
		}
	}
	
	 /**
	  * True if there's a need to request token from the payment gateway. 
	  * 
	  */
	abstract protected function needRequestForToken();

	 /* returns the OkPage.
	 * 
	 * @param $request - the request that got from whtn the customer filed his personal details.
	 * 
	 */
	protected function getOkPage($request) {
		$okTemplate = Billrun_Factory::config()->getConfigValue('PaymentGateways.ok_page');
		$pageRoot = $request->getServer()['HTTP_HOST'];
		$protocol = empty($request->getServer()['HTTPS']) ? 'http' : 'https';
		$okPage = sprintf($okTemplate, $protocol, $pageRoot, $this->billrunName);

		return $okPage;
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
	abstract protected function buildPostArray($aid, $returnUrl, $okPage, $failPage);

	/**
	 *  Build request to Query for getting transaction details.
	 * 
	 * @param string $txId - String that represents the transaction.
	 * @param Array $additionalParams - additional parameters that's needed for integration. 
	 * @return array - represents the request
	 */
	abstract protected function buildTransactionPost($txId, $additionalParams);

	/**
	 * Get the name of the parameter that the payment gateway returns to represent billing agreement id.
	 * 
	 * @return String - the name.
	 */
	abstract public function getTransactionIdName();

	/**
	 * True in case of success in the process of adding payment gateway, dealing with what to do in case of failure.
	 * 
	 */
	abstract public function handleOkPageData($txId);
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
	 * Converting between amount units.
	 * 
	 * @param Float $amount - The sending amount to charge in the gateway.
	 * @return Float - the converted amount.
	 */
	abstract protected function convertAmountToSend($amount);

/**
	 * True if need to call http_build_query before sending the request. 
	 * 
	 */
	abstract protected function isNeedAdjustingRequest();
	
	/**
	 * True if the redirection to the payment gateway hosted page is with a given url. 
	 * 
	 */
	abstract protected function isUrlRedirect();
		
	/**
	 * True if the redirection to the payment gateway hosted page is through printing html form.
	 * 
	 */
	abstract protected function isHtmlRedirect();
	
	/**
	 * Checks that it's all the necessary details for charging exist.
	 * 
	 * @param Array $gateway - array with payment gateway details.
	 * @return Boolean - True if valid structure of the payment gateway.
	 */
	abstract protected function validateStructureForCharge($gatewayDetails); 
	
	/**
	 * Handles errors that come back from the payment gateway.
	 * 
	 * @param $response - response from the payment gateway to the request for token.
	 * 
	 * return Boolean - True if there's an error that was handled. 
	 */
	abstract protected function handleTokenRequestError($response, $params);
	
	/**
	 * Build request for start a transaction of making single payment.
	 * 
	 * @param Int $params - Relevant parameters
	 * @return array - represents the request
	 */
	abstract protected function buildSinglePaymentArray($params, $options);

		/**
	 * Redirect to the payment gateway page of card details.
	 * 
	 * @param $aid - Account id of the client.
	 * @param $returnUrl - The page to redirect the client after success of the whole process.
	 * @param $okPage - the page to return after the customer filed payment details.
	 * 
	 * @return  response from the payment gateway.
	 */
	protected function getToken($aid, $returnUrl, $okPage, $failPage, $singlePaymentParams, $options, $maxTries = 10) {
		if ($maxTries < 0) {
			throw new Exception("Payment gateway error, number of requests for token reached it's limit");
		}
		if (!empty($singlePaymentParams)) {
			$singlePaymentParams['aid'] = $aid;
			$singlePaymentParams['return_url'] = $returnUrl;
			$singlePaymentParams['ok_page'] = $okPage;
			$singlePaymentParams['fail_page'] = $failPage;
			$postArray = $this->buildSinglePaymentArray($singlePaymentParams, $options);
		} else { // Request to get token
			$postArray = $this->buildPostArray($aid, $returnUrl, $okPage, $failPage);
		}
		if ($this->isNeedAdjustingRequest()){
			$postString = http_build_query($postArray);
		} else {
			$postString = $postArray;
		}
		if (function_exists("curl_init")) {
			Billrun_Factory::log("Requesting token from " . $this->billrunName . " for account " . $aid, Zend_Log::DEBUG);
			$result = Billrun_Util::sendRequest($this->EndpointUrl, $postString, Zend_Http_Client::POST, array('Accept-encoding' => 'deflate'), null, 0);
			if ($this->handleTokenRequestError($result, array('aid' => $aid, 'return_url' => $returnUrl, 'ok_page' => $okPage))) {
				$response = $this->getToken($aid, $returnUrl, $okPage, $failPage, $singlePaymentParams, $options, $maxTries - 1);
			} else {
				$response = $result;
			}
		}
		
		return $response;
	}

	/**
	 * Saving Details to Subscribers collection and redirect to our success page or the merchant page if suppiled.
	 * 
	 * @param $txId - String that represents the transaction.
	 */
	public function saveTransactionDetails($txId, $additionalParams) {
		$postArray = $this->buildTransactionPost($txId, $additionalParams);
		if ($this->isNeedAdjustingRequest()){
			$postString = http_build_query($postArray);
		} else {
			$postString = $postArray;
		}
		$this->saveDetails['aid'] = $this->getAidFromProxy($txId);
		$tenantUrl = $this->getTenantReturnUrl($this->saveDetails['aid']);
		$this->updateReturnUrlOnEror($tenantUrl);
		if (function_exists("curl_init") && $this->isTransactionDetailsNeeded()) {
			$result = Billrun_Util::sendRequest($this->EndpointUrl, $postString, Zend_Http_Client::POST, array('Accept-encoding' => 'deflate'), null, 0);
			if (($retParams = $this->getResponseDetails($result)) === FALSE) {
				Billrun_Factory::log("Error: Redirecting to " . $this->returnUrlOnError, Zend_Log::ALERT);
				throw new Exception('Operation Failed. Try Again...');
			}
		}
		if (!$this->validatePaymentProcess($txId)) {
			Billrun_Factory::log("Error: Redirecting to " . $this->returnUrlOnError . ' message: Too much time passed', Zend_Log::ALERT);
			throw new Exception('Too much time passed');
		}

		if (isset($retParams['action']) && $retParams['action'] == 'SinglePayment') {
			$this->paySinglePayment($retParams);
		} else {
			$this->savePaymentGateway();
		}
		
		return array('tenantUrl' => $tenantUrl, 'creditCard' => $retParams['four_digits'], 'expirationDate' => $retParams['expiration_date']);
	}

	/**
	 * Saving payment gateway structure to the relevant account.
	 * 
	 */
	protected function savePaymentGateway() {
		$query = Billrun_Utils_Mongo::getDateBoundQuery();
		$query['aid'] = (int) $this->saveDetails['aid'];
		$query['type'] = "account";
		$setQuery = $this->buildSetQuery();
		if (!$this->validateStructureForCharge($setQuery['payment_gateway.active'])) {
			throw new Exception("Non valid payment gateway for aid = " . $query['aid'], Zend_Log::ALERT);
		}
		Billrun_Factory::log('Saving payment gateway ' . $setQuery['payment_gateway.active']['name'] . ' for ' . $query['aid'], Zend_Log::DEBUG);
		$this->subscribers->update($query, array('$set' => $setQuery));
		Billrun_Factory::log($setQuery['payment_gateway.active']['name'] . " was defined successfully for " . $query['aid'], Zend_Log::INFO);
	}

	protected function signalStartingProcess($aid, $timestamp) {
		$paymentColl = Billrun_Factory::db()->creditproxyCollection();
		$query = array("name" => $this->billrunName, "tx" => (string) $this->transactionId, "stamp" => md5($timestamp . $this->transactionId), "aid" => (int)$aid);
		$textualQuery = json_encode($query);
		Billrun_Factory::log('Querying creditproxy with ' . $textualQuery, Zend_Log::DEBUG);
		$paymentRow = $paymentColl->query($query)->cursor()->current();
		Billrun_Factory::log('Finished querying creditproxy', Zend_Log::DEBUG);
		if (!$paymentRow->isEmpty()) {
			if (isset($paymentRow['done'])) {
				return;
			}
			return;
		}

		// Signal start process
		$query['t'] = $timestamp;
		Billrun_Factory::log('Inserting to creditproxy object ' . $textualQuery, Zend_Log::DEBUG);
		$paymentColl->insert($query);
		Billrun_Factory::log('Finished inserting to creditproxy', Zend_Log::DEBUG);
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
		$query = array("name" => $this->billrunName, "tx" => (string) $txId, "aid" => (int)$this->saveDetails['aid']);
		$paymentRow = $paymentColl->query($query)->cursor()->sort(array('t' => -1))->limit(1)->current();
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
		$query = array("name" => $this->billrunName, "tx" => (string) $txId);
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
	public static function checkPaymentStatus($status, $gateway, $params = array()) {
		if ($gateway->isCompleted($status)) {
			return array('status' => $status, 'stage' => "Completed", 'additional_params' => $params);
		} else if ($gateway->isPending($status)) {
			return array('status' => $status, 'stage' => "Pending", 'additional_params' => $params);
		} else if ($gateway->isRejected($status)) {
			return array('status' => $status, 'stage' => "Rejected", 'additional_params' => $params);
		} else {
			throw new Exception("Unknown status");
		}
	}
	
	/**
	 * Get the Credentials of the current payment gateway. 
	 * 
	 * @return Array - the status and stage of the payment.
	 */
	public function getGatewayCredentials() {
		$gateways = Billrun_Factory::config()->getConfigValue('payment_gateways');
		$gatewayName = $this->billrunName;
		$gateway = array_filter($gateways, function($paymentGateway) use ($gatewayName) {
			return $paymentGateway['name'] == $gatewayName;
		});
		$gatewayDetails = current($gateway);
		return $gatewayDetails['params'];
	}
	
	/**
	 * Get the export details of the current payment gateway. 
	 * 
	 * @return Array - the status and stage of the payment.
	 */
	public function getGatewayExport() {
		$gateways = Billrun_Factory::config()->getConfigValue('payment_gateways');
		$gatewayName = $this->billrunName;
		$gateway = array_filter($gateways, function($paymentGateway) use ($gatewayName) {
			return $paymentGateway['name'] == $gatewayName;
		});
		$gatewayDetails = current($gateway);
		return $gatewayDetails['export'];
	}
	
		/**
	 * Get the receiver details of the current payment gateway. 
	 * 
	 * @return Array - the status and stage of the payment.
	 */
	public function getGatewayReceiver() {
		$gateways = Billrun_Factory::config()->getConfigValue('payment_gateways');
		$gatewayName = $this->billrunName;
		$gateway = array_filter($gateways, function($paymentGateway) use ($gatewayName) {
			return $paymentGateway['name'] == $gatewayName;
		});
		$gatewayDetails = current($gateway);
		return $gatewayDetails['receiver'];
	}
	
	public static function getCustomers($aids = array(), $specificInvoices = FALSE) {
		$billsColl = Billrun_Factory::db()->billsCollection();
		if (!empty($aids)) {
			$match = array(
				'$match' => array(
					'aid' => array('$in' => $aids),
				),
			);
			if (!empty($specificInvoices)) {
				$match['$match']['invoice_id'] = ['$in' => $specificInvoices];
			}
		}
		$match['$match']['$or'] = array(
				array('due_date' => array('$exists' => false)),
				array('due_date' => array('$lt' => new MongoDate())),
		);
		$pipelines[] = $match;
		$pipelines[] = array(
			'$sort' => array(
				'type' => 1,
				'due_date' => -1,
			),
		);
		$pipelines[] = array(
			'$addFields' => array(
				'method' => array('$ifNull' => array('$method', '$payment_method')),
			),	
		);
		
		$pipelines[] = array(
			'$group' => !empty($specificInvoices) ? self::getGroupByMode('byInvoiceId') : self::getGroupByMode(),
		);
		$pipelines[] = array(
			'$match' => array(
				'$or' => array(
					array('due' => array('$gt' => Billrun_Bill::precision)),
					array('due' => array('$lt' => -Billrun_Bill::precision)),
				),
				'suspend_debit' => NULL,
			),
		);
		$res = $billsColl->aggregate($pipelines);
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
	public function isPending($status) {
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
	
	/**
	 * adding params that the payment gateway needs for further integraion.
	 * 
	 */
	public function addAdditionalParameters() {
		return array();
	}
	
	protected function isTransactionDetailsNeeded() {
		return true;
	}
	
	public function isUpdatePgChangesNeeded() {
		return false;
	}
	
	protected function checkIfCustomerExists () {
		return false;
	}
	
	/**
	 * Returns True if there is a need to update the account's payment gateway structure.
	 * 
	 * @param array $params - array of gateway parameters
	 * @return Boolean - True if update needed.
	 */
	public function needUpdateFormerGateway($params) {
		return false;
	}
	
	/**
	 * Updates the url to return to in case of unrecoverable error.
	 * 
	 * @param string $url - the url to return to.
	 * 
	 */
	protected function updateReturnUrlOnEror($url) {
		$this->returnUrlOnError = $url;
	}
	
	/**
	 * Returns the return url defined by the tenant.
	 * 
	 * @param $aid - account Id
	 * @return String - tenant defined url.
	 */
	protected function getTenantReturnUrl($aid) {
		$this->subscribers = Billrun_Factory::db()->subscribersCollection();
		$query = Billrun_Utils_Mongo::getDateBoundQuery();
		$query['aid'] = (int) $aid;
		$query['type'] = "account";
		$account = $this->subscribers->query($query)->cursor()->current();
		return $account['tenant_return_url'];
	}
	
	/**
	 * Checks if the it's chargeable payment gateway. 
	 * @param Array $gatewayDetails - array with payment gateway details.
	 *
	 * @return Boolean - True if it's possible to charge according to the passed details.
	 */
	public static function isValidGatewayStructure($gatewayDetails) {
		if (empty($gatewayDetails) || empty($gatewayDetails['name'])) {
			return false;
		}
		$gateway = self::getInstance($gatewayDetails['name']);
		return $gateway->validateStructureForCharge($gatewayDetails);
	}
			
	public function getReturnUrlOnError() {
		return $this->returnUrlOnError;
	}
	
	public function getReceiverParameters() {
		return array();
	}

	public function getExportParameters() {
		return array();
	}

	public function makeOnlineTransaction($gatewayDetails) {
		$amountToPay = $gatewayDetails['amount'];
		if ($amountToPay > 0) {
			return $this->pay($gatewayDetails);
		} else {
			return $this->credit($gatewayDetails);
		}
	}
	
	protected function credit($gatewayDetails) {
		throw new Exception("Negative amount is not supported in " . $this->billrunName);
	}
	
	protected static function getGroupByMode($mode = false) {
		$group = array(
				'_id' => '$aid',
				'suspend_debit' => array(
					'$first' => '$suspend_debit',
				),
				'type' => array(
					'$first' => '$type',
				),
				'payment_method' => array(
					'$first' => '$method',
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
			);	
		if ($mode == 'byInvoiceId') {
			$group['_id'] = '$invoice_id';
			$group['left_to_pay'] = array('$first' => '$left_to_pay');
			$group['left'] = array('$first' => '$left');
			$group['invoice_id'] = array('$first' => '$invoice_id');
		}	
			
		return $group;
	}
	
	public function handleTransactionRejectionCases($responseFromGateway, $paymentParams) {
		return false;
	}
	
	protected function paySinglePayment($retParams) {
		$options = array('collect' => true, 'payment_gateway' => true, 'single_payment_gateway' => true);
		$query = Billrun_Utils_Mongo::getDateBoundQuery();
		$query['aid'] = $this->saveDetails['aid'];
		$query['type'] = "account";
		$account = Billrun_Factory::account();
		$account->load(array('aid' => $this->saveDetails['aid']));
		$gatewayDetails = $account->payment_gateway['active'];
		$accountId = $account->aid;
		if (!Billrun_PaymentGateway::isValidGatewayStructure($gatewayDetails)) {
			throw new Exception("Non valid payment gateway for aid = " . $accountId);
		}
		if (!isset($retParams['transferred_amount'])) {
			throw new Exception("Missing amount for single payment, aid = " . $accountId);
		}
		$cashAmount = $retParams['transferred_amount'];
		$paymentParams['aid'] = $accountId;
		$paymentParams['billrun_key'] = Billrun_Billingcycle::getBillrunKeyByTimestamp();
		$paymentParams['amount'] = abs($cashAmount);
		$gatewayDetails['amount'] = $cashAmount;
		$gatewayDetails['currency'] = Billrun_Factory::config()->getConfigValue('pricing.currency');	
		$paymentParams['gateway_details'] = $retParams;
		$paymentParams['gateway_details']['name'] = $gatewayDetails['name'];
		$paymentParams['transaction_status'] = $retParams['transaction_status'];
		$paymentParams['dir'] = 'fc';
		Billrun_Factory::log("Creating bill for single payment: Account id=" . $accountId . ", Amount=" . $cashAmount, Zend_Log::INFO);
		Billrun_Bill_Payment::payAndUpdateStatus('automatic', $paymentParams, $options);
	}
	
	public function getCompletionCodes() {
		return $this->completionCodes;
	}
}
