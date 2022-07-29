<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2022 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

use Payrexx\Models\Request\SignatureCheck;
use Payrexx\Models\Request\Transaction;
use Payrexx\Payrexx;
use Payrexx\PayrexxException;

/**
 * This class represents a payment gateway
 *
 * @since    5.13
 */
class Billrun_PaymentGateway_Payrexx extends Billrun_PaymentGateway {

	const DEFAULT_CURRENCY = 'CHF';

	/**
	 * @inheritDoc
	 */
	protected $omnipayName = "Payrexx";

	protected $billrunName = "Payrexx";

	protected $pendingCodes = '/^authorized$/';
	protected $completionCodes = '/^confirmed$/';

	public function __construct() {
		parent::__construct();
		$credentials = $this->getGatewayCredentials();
		$this->omnipayGateway->setApiKey($credentials['instance_api_secret']);
		$this->omnipayGateway->setInstance($credentials['instance_name']);
	}

	public function getDefaultParameters() {
		$params = array("instance_name", "instance_api_secret");
		return $this->rearrangeParametres($params);
	}

	public function getSecretFields() {
		return array('instance_api_secret');
	}

	/**
	 * @inheritDoc
	 */
	public function authenticateCredentials($params) {
		try {
			$payrexx = new Payrexx($params['instance_name'], $params['instance_api_secret']);

			$signatureCheck = new SignatureCheck();
			$payrexx->getOne($signatureCheck);
		} catch (PayrexxException $e) {
			Billrun_Factory::log('Payrexx credentials validation failed with message: ' . $e->getMessage(), Zend_Log::DEBUG);
			return false;
		}

		return true;
	}

	/**
	 * @inheritDoc
	 */
	protected function needRequestForToken() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	protected function getToken($aid, $returnUrl, $okPage, $failPage, $singlePaymentParams, $options, $maxTries = 10) {
		$account = Billrun_Factory::account();
		$account->loadAccountForQuery(array('aid' => (int) $aid));

		$this->transactionId  = Billrun_Util::generateRandomNum();

		if (!empty($singlePaymentParams)) {
			$this->saveDetails['charge'] = 1;
		}

		$request = $this->omnipayGateway->purchase([
			'amount' => $singlePaymentParams['amount'] ?? 0.5, // TODO set from config
//			'vatRate' => 7.7, // TODO ask about vat rate
			'currency' => Billrun_Factory::config()->getConfigValue('pricing.currency', self::DEFAULT_CURRENCY),
			'sku' => 'P01122000', // TODO no sku?
			'preAuthorization' => 1,
			'pm' => ['visa', 'mastercard'],
//			'referenceId' => $aid, // TODO use reference?
			'forename' => $account->firstname,
			'surname' => $account->lastname,
			'email' => $account->email,
			'successRedirectUrl' => $this->adjustRedirectUrl($okPage, $this->transactionId),
			'failedRedirectUrl' => $this->adjustRedirectUrl($failPage, $this->transactionId),
			'buttonText' => 'Add Card'
		]);

		return $request->send();
	}

	protected function adjustRedirectUrl($url, $txId): string {

		$params = http_build_query([
			'name' => $this->billrunName,
			'txId' => $txId
		]);

		return $url . (strpos($url, '?') !== false ? '&' : '?') . $params;
	}

	/**
	 * @inheritDoc
	 */
	protected function isUrlRedirect() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	protected function isHtmlRedirect() {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	protected function updateRedirectUrl($result) {
		$this->redirectUrl = $result->getRedirectUrl();
		// TODO move it to updateSessionTransactionId()
		$this->saveDetails['ref'] = $result->getTransactionReference();
	}

	/**
	 * @inheritDoc
	 */
	function updateSessionTransactionId() {
		// it's updated in updateRedirectUrl()
	}

	protected function signalStartingProcess($aid, $timestamp) {
		parent::signalStartingProcess($aid, $timestamp);

		$paymentColl = Billrun_Factory::db()->creditproxyCollection();
		$query = array("name" => $this->billrunName, "tx" => (string) $this->transactionId, "stamp" => md5($timestamp . $this->transactionId), "aid" => (int)$aid);

		$paymentRow = $paymentColl->query($query)->cursor()->sort(array('t' => -1))->limit(1)->current();
		if ($paymentRow->isEmpty()) {
			return;
		}

		$paymentRow['ref'] = $this->saveDetails['ref'];
		$paymentRow['charge'] = $this->saveDetails['charge'];

		$paymentColl->updateEntity($paymentRow);
	}

	/**
	 * @inheritDoc
	 */
	public function getTransactionIdName() {
		return 'txId';
	}

	/**
	 * @inheritDoc
	 */
	public function handleOkPageData($txId) {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function saveTransactionDetails($txId, $additionalParams) {
		$this->saveDetails['aid'] = $this->getAidFromProxy($txId);
		// TODO tenant URL?
		$tenantUrl = $this->getTenantReturnUrl($this->saveDetails['aid']);
		$this->updateReturnUrlOnEror($tenantUrl);

		$paymentColl = Billrun_Factory::db()->creditproxyCollection();
		$query = array("name" => $this->billrunName, "tx" => (string) $txId);
		$paymentRow = $paymentColl->query($query)->cursor()->current();

		$request = $this->omnipayGateway->completePurchase(['transactionReference' => $paymentRow['ref']]);

		$result = $request->send();

		if (($paymentInfo = $this->getResponseDetails($result)) === FALSE) {
			Billrun_Factory::log("Error: Redirecting to " . $this->returnUrlOnError, Zend_Log::ALERT);
			throw new Exception('Operation Failed. Try Again...');
		}

		if (!$this->validatePaymentProcess($txId)) {
			Billrun_Factory::log("Error: Redirecting to " . $this->returnUrlOnError . ' message: Too much time passed', Zend_Log::ALERT);
			throw new Exception('Too much time passed');
		}

		$this->savePaymentGateway();

		if ($paymentRow['charge']) {
			$this->paySinglePayment($paymentInfo);
		}

		return array(
			'tenantUrl' => $tenantUrl,
			'creditCard' => $paymentInfo['four_digits'],
			'expirationDate' => $paymentInfo['expiration_date']
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function getResponseDetails($result) {
		$gatewayInfo = $result->getData();
		if (!isset($gatewayInfo->getInvoices()[0]["transactions"][0])) {
			throw new Exception('Wrong response from payment gateway');
		}
		$transaction = $gatewayInfo->getInvoices()[0]["transactions"][0];

		$this->saveDetails['card_token'] = $transaction['id'];
		$this->saveDetails['four_digits'] = !empty($transaction['payment']['cardNumber'])
			? substr($transaction['payment']['cardNumber'], -4) : '';
		$this->saveDetails['card_expiration'] = $transaction['payment']['expiry'];
		$amount = $gatewayInfo->getAmount() / 100;

		return [
			'card_token' => $this->saveDetails['card_token'],
			'four_digits' => $this->saveDetails['four_digits'],
			'expiration_date' => $this->saveDetails['card_expiration'],
			'transferred_amount' => $amount, // TODO check if it's necessary
			'amount' => $amount,
			"transaction_status" => $gatewayInfo->getStatus()
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function buildSetQuery() {
		return array(
			'active' => array(
				'name' => $this->billrunName,
				'card_token' => (string) $this->saveDetails['card_token'],
				'card_expiration' => (string) $this->saveDetails['card_expiration'],
				'transaction_exhausted' => true,
				'generate_token_time' => new MongoDate(time()),
				'four_digits' => (string) $this->saveDetails['four_digits'],
			)
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function validateStructureForCharge($structure) {
		return !empty($structure['card_token']);
	}

	protected function paySinglePayment($retParams) {
		$options = array('collect' => true, 'payment_gateway' => true);
		$gatewayDetails = $this->saveDetails;
		$gatewayDetails['name'] = $this->billrunName;
		$accountId = $this->saveDetails['aid'];
		if (!Billrun_PaymentGateway::isValidGatewayStructure($gatewayDetails)) {
			Billrun_Factory::log("Non valid payment gateway for aid = " . $accountId, Zend_Log::NOTICE);
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
		$paymentParams['gateway_details']['name'] = !empty($gatewayDetails['name']) ? $gatewayDetails['name'] : $this->billrunName;
		$paymentParams['transaction_status'] = $retParams['transaction_status'];
		if (isset($retParams['installments'])) {
			$paymentParams['installments'] = $retParams['installments'];
		}
		$paymentParams['dir'] = 'fc';
		if (isset($retParams['payment_identifier'])) {
			$options['additional_params']['payment_identifier'] = $retParams['payment_identifier'];
		}
		Billrun_Factory::log("Creating bill for single payment: Account id=" . $accountId . ", Amount=" . $cashAmount, Zend_Log::INFO);
		Billrun_Bill_Payment::payAndUpdateStatus('automatic', $paymentParams, $options);
	}

	/**
	 * @inheritDoc
	 */
	protected function pay($gatewayDetails, $addonData) {
		$credentials = $this->getGatewayCredentials();

		try {
			$payrexx = new Payrexx($credentials['instance_name'], $credentials['instance_api_secret']);

			$transaction = new Transaction();
			$transaction->setId($gatewayDetails['card_token']);
			$transaction->setAmount($gatewayDetails['amount'] * 100); // convert to cents
			$response = $payrexx->charge($transaction);
		} catch (PayrexxException $e) {
			Billrun_Factory::log('Payrexx credentials validation failed with message: ' . $e->getMessage(), Zend_Log::DEBUG);
			return false;
		}


		$this->transactionId = $response->getId();

		return [
			'status' => $response->getStatus(),
			'additional_params' => []
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function buildPostArray($aid, $returnUrl, $okPage, $failPage) {
		throw new Exception("Method " . __METHOD__);
		// TODO: Implement buildPostArray() method.
	}

	/**
	 * @inheritDoc
	 */
	protected function buildTransactionPost($txId, $additionalParams) {
		throw new Exception("Method " . __METHOD__);
		// TODO: Implement buildTransactionPost() method.
	}

	/**
	 * @inheritDoc
	 */
	public function verifyPending($txId) {
		throw new Exception("Method " . __METHOD__);
		// TODO: Implement verifyPending() method.
	}

	/**
	 * @inheritDoc
	 */
	public function hasPendingStatus() {
		throw new Exception("Method " . __METHOD__);
		// TODO: Implement hasPendingStatus() method.
	}

	/**
	 * @inheritDoc
	 */
	protected function convertAmountToSend($amount) {
		throw new Exception("Method " . __METHOD__);
		// TODO: Implement convertAmountToSend() method.
	}

	/**
	 * @inheritDoc
	 */
	protected function isNeedAdjustingRequest() {
		throw new Exception("Method " . __METHOD__);
		// TODO: Implement isNeedAdjustingRequest() method.
	}

	/**
	 * @inheritDoc
	 */
	protected function handleTokenRequestError($response, $params) {
		throw new Exception("Method " . __METHOD__);
		// TODO: Implement handleTokenRequestError() method.
	}

	/**
	 * @inheritDoc
	 */
	protected function buildSinglePaymentArray($params, $options) {
		throw new Exception("Method " . __METHOD__);
		// TODO: Implement buildSinglePaymentArray() method.
	}

	/**
	 * @inheritDoc
	 */
	public function createRecurringBillingProfile($aid, $gatewayDetails, $params = []) {
		throw new Exception("Method " . __METHOD__);
		// TODO: Implement createRecurringBillingProfile() method.
	}

}