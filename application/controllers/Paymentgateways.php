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
			if (is_null($paymentGateway)){
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
	//	$data = $this->validateData($request);
	$data = $request->get('data');
		if ($data === null) {
			return $this->setError("Failed to authenticate", $request);
		}
		$jsonData = json_decode($data, true);
		if (!isset($jsonData['aid']) || is_null(($aid = $jsonData['aid'])) || !Billrun_Util::IsIntegerValue($aid)) {
			return $this->setError("need to pass numeric aid", $request);
		}

		if (!isset($jsonData['t']) || is_null(($timestamp = $jsonData['t']))) {
			return $this->setError("Invalid arguments", $request);
		}

		if (!isset($jsonData['name'])) {
			return $this->$setError("need to pass payment gateway name", $request);
		}
		$name = $jsonData['name'];
		$aid = $jsonData['aid'];
		$returnUrl = $jsonData['return_url'];
		if (empty($returnUrl)) {
			$returnUrl = Billrun_Factory::config()->getConfigValue('return_url'); 
		}

		$paymentGateway = Billrun_PaymentGateway::getInstance($name);
		$paymentGateway->redirectForToken($aid, $returnUrl, $timestamp);
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
	

	public function payAction() {  
		$request = $this->getRequest();
		$stamp = $request->get('stamp'); 
		if (is_null($stamp) || !Billrun_Util::isBillrunKey($stamp)){
			return $this->setError("Illegal stamp", $request);
		}	
		Billrun_PaymentGateway::makePayment($stamp);
	}
	
	
	public function successAction() {  
		print_r("SUCCESS"); 
	}
	
	/**
	 * Validates the input data.
	 * @return data - Request data if validated, null if error.
	 */
	public function validateData($request) {
		$data = $request->get("data");
		$signature = $request->get("signature");
		if (empty($signature)) {
			return false;
		}

		// Get the secret
		$secret = Billrun_Factory::config()->getConfigValue("shared_secret.key");
		if (!$this->validateSecret($secret)) {
			return null;
		}

		$hashResult = hash_hmac("sha512", $data, $secret);

		// state whether signature is okay or not
		$validData = null;

		if (hash_equals($signature, $hashResult)) {
			$validData = $data;
		}
		return $validData;
	}

	protected function validateSecret($secret) {
		if (empty($secret) || !is_string($secret)) {
			return false;
		}
		$crc = Billrun_Factory::config()->getConfigValue("shared_secret.crc");
		$calculatedCrc = hash("crc32b", $secret);

		// Validate checksum
		return hash_equals($crc, $calculatedCrc);
	}

}
