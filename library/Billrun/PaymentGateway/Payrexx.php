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
	}

	/**
	 * @inheritDoc
	 */
	protected function getToken($aid, $returnUrl, $okPage, $failPage, $singlePaymentParams, $options, $maxTries = 10) {
	}

	/**
	 * @inheritDoc
	 */
	protected function isUrlRedirect() {
	}

	/**
	 * @inheritDoc
	 */
	protected function isHtmlRedirect() {
	}

	/**
	 * @inheritDoc
	 */
	protected function updateRedirectUrl($result) {
	}

	/**
	 * @inheritDoc
	 */
	function updateSessionTransactionId() {
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