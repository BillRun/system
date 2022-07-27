<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2022 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

use Payrexx\Models\Request\SignatureCheck;
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

		$request = $this->omnipayGateway->purchase([
			'amount' => 1, // TODO set from config
//			'vatRate' => 7.7, // TODO ask about vat rate
			'currency' => self::DEFAULT_CURRENCY,
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

		if (($retParams = $this->getResponseDetails($result)) === FALSE) {
			Billrun_Factory::log("Error: Redirecting to " . $this->returnUrlOnError, Zend_Log::ALERT);
			throw new Exception('Operation Failed. Try Again...');
		}

		if (!$this->validatePaymentProcess($txId)) {
			Billrun_Factory::log("Error: Redirecting to " . $this->returnUrlOnError . ' message: Too much time passed', Zend_Log::ALERT);
			throw new Exception('Too much time passed');
		}

		$this->savePaymentGateway();

		return array('tenantUrl' => $tenantUrl, 'creditCard' => $retParams['four_digits'], 'expirationDate' => $retParams['expiration_date']);
	}

	/**
	 * @inheritDoc
	 */
	protected function getResponseDetails($result) {
		if (!isset($result->getData()->getInvoices()[0]["transactions"][0])) {
			throw new Exception('Wrong response from payment gateway');
		}
		$transaction = $result->getData()->getInvoices()[0]["transactions"][0];

		$this->saveDetails['card_token'] = $transaction['id'];
		$this->saveDetails['four_digits'] = !empty($transaction['payment']['cardNumber'])
			? substr($transaction['payment']['cardNumber'], -4) : '';
		$this->saveDetails['card_expiration'] = $transaction['payment']['expiry'];

		// TODO format four_digits and expiration_date properly
		return [
			'four_digits' => $this->saveDetails['four_digits'],
			'expiration_date' => $this->saveDetails['card_expiration']
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
	protected function pay($gatewayDetails, $addonData) {
		throw new Exception("Method " . __METHOD__);
		// TODO: Implement pay() method.
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