<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';
require_once APPLICATION_PATH . '/library/vendor/autoload.php';
require_once APPLICATION_PATH . '/application/controllers/Action/Pay.php';
require_once APPLICATION_PATH . '/application/controllers/Action/Collect.php';

/**
 * This class returns the available payment gateways in Billrun.
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       5.2
 */
class PaymentGatewaysController extends ApiController {

	public function init() {
		parent::init();
	}

	public function listAction() {
		$gateways = Billrun_Factory::config()->getConfigValue('PaymentGateways.potential');
		$imagesUrl = Billrun_Factory::config()->getConfigValue('PaymentGateways.images');
		$settings = array();
		foreach ($gateways as $name) {
			$setting = array();
			$setting['name'] = $name;
			$setting['supported'] = true;
			$setting['image_url'] = $imagesUrl[$name];
			$paymentGateway = Billrun_Factory::paymentGateway($name);
			if (is_null($paymentGateway)) {
				$setting['supported'] = false;
				$settings[] = $setting;
				break;
			}
			$fields = $paymentGateway->getDefaultParameters();
			$setting['params'] = $fields;
			$settings[] = $setting;
		}
		$this->setOutput(array(
			'status' => !empty($settings) ? 1 : 0,
			'desc' => !empty($settings) ? 'success' : 'error',
			'details' => empty($settings) ? array() : $settings,
		));
	}

	protected function render($tpl, array $parameters = array()) {
		return parent::render('index', $parameters);
	}

	/**
	 * Request for transaction with the chosen payment gateway for getting billing agreement id.
	 * 
	 */
	public function getRequestAction() {
		$request = $this->getRequest();
		// Validate the data.
		$requestData = json_decode($request->get('data'), true);
		$data = Billrun_Utils_Security::validateData($requestData);
		if ($data === null) {
			return $this->setError("Failed to authenticate", $request);
		}

		if (!isset($data['aid']) || is_null(($aid = $data['aid'])) || !Billrun_Util::IsIntegerValue($aid)) {
			return $this->setError("need to pass numeric aid", $request);
		}

		if (!isset($data['t']) || is_null(($timestamp = $data['t']))) {
			return $this->setError("Invalid arguments", $request);
		}

		if (!isset($data['name'])) {
			return $this->setError("need to pass payment gateway name", $request);
		}

		$name = $data['name'];
		$aid = $data['aid'];
		$this->validatePaymentGateway($name, $aid);

		if (isset($data['return_url'])) {
			$returnUrl = $data['return_url'];
		} else {
			$returnUrl = Billrun_Factory::config()->getConfigValue('billrun.return_url');
		}
		if (empty($returnUrl)) {
			$returnUrl = Billrun_Factory::config()->getConfigValue('PaymentGateways.success_url');
		}
		$today = new MongoDate();
		$subscribers = Billrun_Factory::db()->subscribersCollection();
		$query = array(
			'tennant_return_url' => $returnUrl,
			'gateway' => $name
		);
		
		$subscribers->update(array('aid' => (int) $aid, 'from' => array('$lte' => $today), 'to' => array('$gte' => $today), 'type' => "account"), array('$set' => $query));
		$paymentGateway = Billrun_PaymentGateway::getInstance($name);
		$paymentGateway->redirectForToken($aid, $returnUrl, $timestamp, $request);
	}

	/**
	 * Get a db query for an active account according to the account id.
	 * @param int $aid - The account id.
	 * @return array The active account query
	 */
	protected function getAccoundQuery($aid) {
		$accountQuery = Billrun_Utils_Mongo::getDateBoundQuery();
		$accountQuery['type'] = 'account';
		$accountQuery['aid'] = $aid;
		return $accountQuery;
	}
	
	/**
	 * Validate that the input payment gateway fits the payment gateway that is
	 * stored in the database with the account.
	 * If the account doesn't have a gateway the validation does not throw an error.
	 * @param string $name - The name of the payment gateway
	 * @param int $aid - The Account identification number
	 * @throws Billrun_Exceptions_InvalidFields Throws an invalid field 
	 * exception if the input is invalid
	 */
	protected function validatePaymentGateway($name, $aid) {
		// Get the accound object.
		$accountQuery = $this->getAccoundQuery($aid);
		$account = Billrun_Factory::db()->subscribersCollection()->query($accountQuery)->cursor()->current();
		if($account && !$account->isEmpty() && isset($account['gateway'])) {
			// Check the payment gateway
			if($account['gateway'] != $name) {
				$invField = new Billrun_DataTypes_InvalidField('payment_gateway');
				throw new Billrun_Exceptions_InvalidFields(array($invField));
			}
		}
	}
	
	/**
	 * handling the response from the payment gateway and saving the details to db.
	 * 
	 */
	public function OkPageAction() {
		$request = $this->getRequest();
		$name = $request->get("name");
		if (is_null($name)) {
			return $this->setError("Missing payment gateway name", $request);
		}
		$paymentGateway = Billrun_PaymentGateway::getInstance($name);
		$transactionName = $paymentGateway->getTransactionIdName();
		$transactionId = $request->get($transactionName);
		if (is_null($transactionId)) {
			return $this->setError("Operation Failed. Try Again...", $request);
		}

		$paymentGateway->saveTransactionDetails($transactionId);
	}

	/**
	 * handling making the payments against the payment gateways and checking status of pending payments.
	 * 
	 */
	public function payAction() {
		$request = $this->getRequest();
		$stamp = $request->get('stamp');
		if (is_null($stamp) || !Billrun_Util::isBillrunKey($stamp)) {
			return $this->setError("Illegal stamp", $request);
		}
		$pendingPayments = $this->loadPending();
		foreach ($pendingPayments as $payment) {
			$gatewayName = $payment['payment_gateway']['name'];
			$paymentGateway = Billrun_PaymentGateway::getInstance($gatewayName);
			if (is_null($paymentGateway) || !$paymentGateway->hasPendingStatus()) {
				continue;
			}
			$txId = $payment['payment_gateway']['transactionId'];
			$status = $paymentGateway->verifyPending($txId);
			$paymentData = Billrun_Bill_Payment::getInstanceByData($payment);
			if ($status == 'Pending') { // Payment is still pending
				$paymentData->updateLastPendingCheck();
				continue;
			}
			$response = $paymentGateway->checkPaymentStatus($status, $paymentGateway);
			Billrun_PaymentGateway::updateAccordingToStatus($response, $paymentData, $gatewayName);
		}
		Billrun_PaymentGateway::makePayment($stamp);
	}

	public function successAction() {
		print_r("SUCCESS");
	}

	/**
	 * Load payments with status pending and that their status had not been checked for some time. 
	 * 
	 */
	protected function loadPending() {
		$billsColl = Billrun_Factory::db()->billsCollection();
		$lastTimeChecked = Billrun_Factory::config()->getConfigValue('PaymentGateways.orphan_check_time');
		$paymentsOrphan = new MongoDate(strtotime('-' . $lastTimeChecked, time()));
		$query = array(
			'waiting_for_confirmation' => true,
			'last_checked_pending' => array('$lte' => $paymentsOrphan)
		);
		$res = $billsColl->query($query);
		return $res;
	}
	
}
