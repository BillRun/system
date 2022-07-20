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
			'successRedirectUrl' => $okPage,
			'failedRedirectUrl' => $failPage,
			'buttonText' => 'Add Card'
		]);

		return $request->send();
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
		// TODO move it to updateSessionTransactionId()?
		$this->transactionId = $result->getTransactionReference();
	}

	/**
	 * @inheritDoc
	 */
	function updateSessionTransactionId() {
		// it's updated in updateRedirectUrl()
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
	public function getTransactionIdName() {
		throw new Exception("Method " . __METHOD__);
		// TODO: Implement getTransactionIdName() method.
	}

	/**
	 * @inheritDoc
	 */
	public function handleOkPageData($txId) {
		throw new Exception("Method " . __METHOD__);
		// TODO: Implement handleOkPageData() method.
	}

	/**
	 * @inheritDoc
	 */
	protected function getResponseDetails($result) {
		throw new Exception("Method " . __METHOD__);
		// TODO: Implement getResponseDetails() method.
	}

	/**
	 * @inheritDoc
	 */
	protected function buildSetQuery() {
		throw new Exception("Method " . __METHOD__);
		// TODO: Implement buildSetQuery() method.
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
	protected function validateStructureForCharge($gatewayDetails) {
		throw new Exception("Method " . __METHOD__);
		// TODO: Implement validateStructureForCharge() method.
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