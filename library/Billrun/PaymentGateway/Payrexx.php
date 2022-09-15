<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2022 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

use Payrexx\Communicator;
use Payrexx\Models\Request\Gateway as GatewayRequest;
use Payrexx\Models\Request\SignatureCheck;
use Payrexx\Models\Request\Transaction;
use Payrexx\Models\Response\Gateway as GatewayResponse;
use Payrexx\Models\Response\Transaction as TransactionResponse;
use Payrexx\Payrexx;
use Payrexx\PayrexxException;

/**
 * This class represents a payment gateway
 *
 * @since    5.13
 */
class Billrun_PaymentGateway_Payrexx extends Billrun_PaymentGateway {

	const DEFAULT_CURRENCY = 'CHF';
	const DEFAULT_PAYMENT_METHODS = [];
	const DEFAULT_AMOUNT = 0.5;
	const API_VERSION = '1.1';

	protected $billrunName = "Payrexx";

	protected $pendingCodes = '/^(waiting|refund_pending)$/';
	protected $completionCodes = '/^(authorized|confirmed|reserved|refunded|partially-refunded)/';
	protected $rejectionCodes = '/^(cancelled|declined)$/';

	public function getDefaultParameters() {
		$params = array('instance_name', 'instance_api_secret', 'custom_api_domain');
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
			$payrexx = $this->getPayrexxClient($params);

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
	 * @return GatewayResponse
	 * @throws PayrexxException
	 */
	protected function getToken($aid, $returnUrl, $okPage, $failPage, $singlePaymentParams, $options, $maxTries = 10) {
		$account = Billrun_Factory::account();
		$account->loadAccountForQuery(array('aid' => (int) $aid));

		$this->transactionId  = Billrun_Util::generateRandomNum();

		if (!empty($singlePaymentParams)) {
			$this->saveDetails['charge'] = 1;
		}

		$gateway = new GatewayRequest();

		$gateway->setAmount($this->convertAmountToSend($singlePaymentParams['amount'] ?? self::DEFAULT_AMOUNT));
		$gateway->setCurrency(Billrun_Factory::config()->getConfigValue('pricing.currency', self::DEFAULT_CURRENCY));
		$gateway->setSuccessRedirectUrl($this->adjustRedirectUrl($okPage, $this->transactionId));
		$gateway->setFailedRedirectUrl($this->adjustRedirectUrl($failPage, $this->transactionId));
		$gateway->setPm(self::DEFAULT_PAYMENT_METHODS);
		$gateway->setPreAuthorization(1);// indicate tokenization procedure

		$gateway->addField('forename', $account->firstname);
		$gateway->addField('surname', $account->lastname);
		$gateway->addField('email', $account->email);

		return $this->getPayrexxClient()->create($gateway);
	}

	protected function adjustRedirectUrl($url, $txId): string {

		$params = http_build_query([
			'name' => $this->instanceName,
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
	 * @param GatewayResponse $result
	 */
	protected function updateRedirectUrl($result) {
		$this->redirectUrl = $result->getLink();
	}

	/**
	 * @inheritDoc
	 * @param GatewayRequest $result
	 */
	function updateSessionTransactionId($result) {
		$this->saveDetails['ref'] = $result->getId();
	}

	protected function signalStartingProcess($aid, $timestamp) {
		parent::signalStartingProcess($aid, $timestamp);

		$paymentColl = Billrun_Factory::db()->creditproxyCollection();
		$query = array(
			"name" => $this->billrunName,
			"instance_name" => $this->instanceName,
			"tx" => (string) $this->transactionId,
			"stamp" => md5($timestamp . $this->transactionId),
			"aid" => (int) $aid
		);

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
	 * Saving Details to Subscribers collection and redirect to our success page or the merchant page if supplied.
	 *
	 * @param $txId
	 * @param $additionalParams
	 * @return array
	 * @throws PayrexxException
	 */
	public function saveTransactionDetails($txId, $additionalParams) {
		$aid = $this->getAidFromProxy($txId);
		$tenantUrl = $this->getTenantReturnUrl($aid);
		$this->updateReturnUrlOnEror($tenantUrl);

		$paymentColl = Billrun_Factory::db()->creditproxyCollection();
		$query = array("name" => $this->billrunName, "instance_name" => $this->instanceName, "tx" => (string) $txId);
		$paymentRow = $paymentColl->query($query)->cursor()->current();

		$gateway = new GatewayRequest();
		$gateway->setId($paymentRow['ref']);
		/** @var GatewayResponse $tokenizationResult */
		$tokenizationResult = $this->getPayrexxClient()->getOne($gateway);

		if (($cardDetails = $this->getCardDetails($tokenizationResult)) === FALSE) {
			Billrun_Factory::log("Error: Redirecting to " . $this->returnUrlOnError, Zend_Log::ALERT);
			throw new Exception('Operation Failed. Try Again...');
		}

		$this->saveDetails['aid'] = $aid; // for validatePaymentProcess(), savePaymentGateway() and paySinglePayment()
		if (!$this->validatePaymentProcess($txId)) {
			Billrun_Factory::log("Error: Redirecting to " . $this->returnUrlOnError . ' message: Too much time passed', Zend_Log::ALERT);
			throw new Exception('Too much time passed');
		}

		$this->saveDetails['card_token'] = $cardDetails['card_token'];
		$this->saveDetails['four_digits'] = $cardDetails['four_digits'];
		$this->saveDetails['card_expiration'] = $cardDetails['expiration_date'];
		$this->savePaymentGateway();

		if ($paymentRow['charge']) {
			$this->chargeOnTokenization($tokenizationResult, $cardDetails);
		}

		return array(
			'tenantUrl' => $tenantUrl,
			'creditCard' => $cardDetails['four_digits'],
			'expirationDate' => $cardDetails['expiration_date']
		);
	}

	/**
	 * Query the response to getting needed details.
	 *
	 * @param TransactionResponse $result
	 * @return array
	 */
	protected function getResponseDetails($result) {
		$amount = $this->convertReceivedAmount($result->getAmount());

		return [
			'payment_identifier' => (string) $result->getId(),
			'transferred_amount' => $amount,
			'transaction_status' => $result->getStatus()
		];
	}

	/**
	 * @param GatewayResponse $result
	 * @return array
	 * @throws Exception
	 */
	protected function getCardDetails($result) {
		$transaction = $this->getAuthorizedTransactionFromGatewayResponse($result);

		$lastDigits = !empty($transaction['payment']['cardNumber'])
			? substr($transaction['payment']['cardNumber'], -4) : '';

		return [
			'card_token' => (string) $transaction['id'],
			'four_digits' => (string) $lastDigits,
			'expiration_date' => (string) $transaction['payment']['expiry']
		];
	}

	/**
	 * @param GatewayResponse $result
	 * @return mixed
	 * @throws Exception
	 */
	private function getAuthorizedTransactionFromGatewayResponse(GatewayResponse $result): array {
		foreach ($result->getInvoices() as $invoice) {
			if (!isset($invoice["transactions"][0])) {
				throw new Exception('Wrong response from payment gateway');
			}
			$transaction = $invoice["transactions"][0];

			if ($transaction['status'] == 'authorized') {
				return $transaction;
			}
		}
		throw new Exception('No authorized transactions in the gateway response');
	}

	/**
	 * @inheritDoc
	 */
	protected function buildSetQuery() {
		return array(
			'active' => array(
				'name' => $this->billrunName,
				'instance_name' => $this->instanceName,
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
	 * @param GatewayResponse $tokenizationResult
	 * @param array $cardDetails
	 * @return void
	 * @throws PayrexxException
	 */
	private function chargeOnTokenization(GatewayResponse $tokenizationResult, array $cardDetails) {
		// do charge
		$amountCents = $tokenizationResult->getAmount(); // in cents
		$paymentResult = $this->chargeCard($cardDetails['card_token'], $amountCents);

		// get standard payment details
		$paymentDetails = $this->getResponseDetails($paymentResult);

		// get charge fee
		$additionalParams = $this->getVendorResponseDetails($paymentResult->getId());

		// complete payment flow
		$this->transactionId = $paymentDetails['payment_identifier']; // for paySinglePayment()
		$this->saveDetails['card_token'] = $cardDetails['card_token']; // for paySinglePayment()
		$this->paySinglePayment($cardDetails + $paymentDetails, $additionalParams);
	}

	/**
	 * @inheritDoc
	 */
	protected function pay($gatewayDetails, $addonData) {
		$response = $this->chargeCard(
			$gatewayDetails['card_token'],
			$this->convertAmountToSend($gatewayDetails['amount'])
		);

		$this->transactionId = $response->getId(); // for outside use

		return [
			'status' => $response->getStatus(),
			'additional_params' => $this->getVendorResponseDetails($response->getId())
		];
	}

	/**
	 * @param int $transactionId
	 * @return float|int
	 * @throws PayrexxException
	 */
	private function getVendorResponseDetails(int $transactionId) {
		$transactionResponse = $this->requestTransaction($transactionId);
		$payrexxFee = $this->convertReceivedAmount($transactionResponse->getPayrexxFee());
		$psp = $transactionResponse->getPsp();
		$paymentMethod = $transactionResponse->getPayment()['brand'];

		return ['fee' => $payrexxFee, 'psp' => $psp, 'pm' => $paymentMethod];
	}

	/**
	 * @param $cardToken
	 * @param $amountCents
	 * @return TransactionResponse
	 * @throws PayrexxException
	 */
	private function chargeCard($cardToken, $amountCents): TransactionResponse {
		$transaction = new Transaction();
		$transaction->setId($cardToken);
		$transaction->setAmount($amountCents);
		$response = $this->getPayrexxClient()->charge($transaction);
		return $response;
	}

	/**
	 * @param int $id
	 * @return mixed
	 * @throws PayrexxException
	 */
	private function requestTransaction(int $id) {
		$payrexx = $this->getPayrexxClient();
		$transaction = new Transaction();
		$transaction->setId($id);

		$transactionResponse = $payrexx->getOne($transaction);
		return $transactionResponse;
	}

	/**
	 * @param array $credentials
	 * @return Payrexx
	 * @throws PayrexxException
	 */
	private function getPayrexxClient(array $credentials = []): Payrexx {
		$credentials = $credentials ?: $this->getGatewayCredentials();

		$payrexx = new Payrexx(
			$credentials['instance_name'],
			$credentials['instance_api_secret'],
			'',
			$credentials['custom_api_domain'] ?? Communicator::API_URL_BASE_DOMAIN,
			self::API_VERSION
		);
		return $payrexx;
	}

	/**
	 * @inheritDoc
	 */
	protected function convertAmountToSend($amount) {
		$amount = round($amount, 2);
		return $amount * 100;
	}

	protected function convertReceivedAmount($amount) {
		return $amount / 100;
	}

	/**
	 * @inheritDoc
	 */
	protected function buildPostArray($aid, $returnUrl, $okPage, $failPage) {
		// not applicable for Payrexx client
		return [];
	}

	/**
	 * @inheritDoc
	 */
	protected function buildTransactionPost($txId, $additionalParams) {
		return [];
	}

	/**
	 * @inheritDoc
	 */
	public function verifyPending($txId) {
		return '';
	}

	/**
	 * @inheritDoc
	 */
	public function hasPendingStatus() {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	protected function isNeedAdjustingRequest() {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	protected function handleTokenRequestError($response, $params) {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	protected function buildSinglePaymentArray($params, $options) {
		// not applicable for Payrexx client
		return [];
	}

	/**
	 * @inheritDoc
	 */
	public function createRecurringBillingProfile($aid, $gatewayDetails, $params = []) {
		return '';
	}

}