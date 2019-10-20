<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';
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
	use Billrun_Traits_Api_PageRedirect;
	use Billrun_Traits_Api_UserPermissions;
	
	public function init() {
		parent::init();
	}

	public function listAction() {
		$this->allowed();
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
				continue;
			}
			$fields = $paymentGateway->getDefaultParameters();
			$setting['params'] = $fields;
			$setting['receiver'] = $paymentGateway->getReceiverParameters();
			$setting['export'] = $paymentGateway->getExportParameters();
			$settings[] = $setting;
		}
		$output = array (
			'status' => !empty($settings) ? 1 : 0,
			'desc' => !empty($settings) ? 'success' : 'error',
			'details' => empty($settings) ? array() : $settings,
		);
		$this->setOutput(array($output));
	}

	protected function render($tpl, array $parameters = null) {
		return parent::render('index', $parameters);
	}

	/**
	 * Request for transaction with the chosen payment gateway for getting billing agreement id.
	 * 
	 */
	public function getRequestAction() {
		$request = $this->getRequest();
		// Validate the data.
		$originalRequestData = $requestData = json_decode($request->get('data'), true);
		if (isset($requestData['return_url'])) {
			$requestData['return_url'] = urlencode($requestData['return_url']);
		}
		if (isset($requestData['ok_page'])) {
			$requestData['ok_page'] = urlencode($requestData['ok_page']);
		}
		if (isset($requestData['fail_page'])) {
			$requestData['fail_page'] = urlencode($requestData['fail_page']);
		}
		if (!Billrun_Utils_Security::validateData($requestData)) {
			return $this->setError("Failed to authenticate", $requestData);
		} else {
			$data = $originalRequestData;
			unset($data[Billrun_Utils_Security::SIGNATURE_FIELD]);
		}

		if (!isset($data['aid']) || is_null(($aid = $data['aid'])) || !Billrun_Util::IsIntegerValue($aid)) {
			return $this->setError("need to pass numeric aid", $request);
		}

		// No need to check isset, the validateData function validates that the 
		// timestamp value exists.
		if (is_null($timestamp = $data[Billrun_Utils_Security::TIMESTAMP_FIELD])) {
			return $this->setError("Invalid arguments", $request);
		}

		if (!isset($data['name'])) {
			return $this->setError("need to pass payment gateway name", $request);
		}
		
		if (isset($data['action']) && ($data['action'] == 'single_payment') && (isset($data['amount']) && $data['amount'] <= 0)) {
			return $this->setError("In single payment mode amount must be positive value", $request);
		}
		
		if (isset($data['installments']) && (!is_array($data['installments']) || !isset($data['installments']['total_amount']) || 
			$data['installments']['total_amount'] <= 0 || !isset($data['installments']['number_of_payments']) || $data['installments']['number_of_payments'] <= 1)) {
			return $this->setError("Installment structure is invalid", $request);
		}

		$name = $data['name'];
		$aid = $data['aid'];
		$iframe = (isset($data['iframe']) && $data['iframe']) ? true : false;

		if (isset($data['return_url'])) {
			$returnUrl = $data['return_url'];
		} else {
			$returnUrl = Billrun_Factory::config()->getConfigValue('billrun.return_url');
		}
		if (empty($returnUrl)) {
			$returnUrl = Billrun_Factory::config()->getConfigValue('PaymentGateways.success_url');
		}
		
		$accountQuery = $this->getAccountQuery($aid);
		$accountQuery['tenant_return_url'] = $returnUrl;
		$paymentGateway = Billrun_PaymentGateway::getInstance($name);
		try {
			$result = $paymentGateway->redirectToGateway($aid, $accountQuery, $timestamp, $request, $data);
		} catch (Exception $e) {
			if ($iframe) {
				$output = array(
					'status' => 0,
					'details' =>  array('message' => $e->getMessage()),
				);
				$this->getView()->outputMethod = array('Zend_Json', 'encode');
				$this->setOutput(array($output));
				return;
			} else {
				$this->forceRedirectWithMessage($paymentGateway->getReturnUrlOnError(), $e->getMessage(), 'danger');
			}
		}
		if ($result['content_type'] == 'url') {
			if ($iframe) {
				$output = array(
					'status' => 1,
					'desc' => 'success',
					'details' => empty($result) ? array() : array('url' => $result['content']),
				);
				$this->getView()->outputMethod = array('Zend_Json', 'encode');
				$this->setOutput(array($output));
			} else {
				$this->getView()->output = $result['content'];
				$this->getView()->outputMethod = 'header';
			}
		} else if ($result['content_type'] == 'html') {
			$this->setOutput(array($result['content'], TRUE));
		}
	}

	/**
	 * Get a db query for an active account according to the account id.
	 * @param int $aid - The account id.
	 * @return array The active account query
	 */
	protected function getAccountQuery($aid) {
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
		$query = $this->getAccountQuery($aid);
		$accountEntity = Billrun_Factory::account();
		$accountEntity->load($query);
		$account = $accountEntity->getCustomerData();
		if($account && !$account->isEmpty() && isset($account['payment_gateway']['active']['name'])) {
			// Check the payment gateway
			if($account['payment_gateway']['active']['name'] != $name) {
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
		$redirect = $request->get("redirect");
		try {
			$handleResponse = $paymentGateway->handleOkPageData($transactionId);
			Billrun_Factory::log("Token received from " . $name . ", transaction: " . $transactionId, Zend_Log::DEBUG);
			if ($handleResponse !== true) {
				$returnUrl = $handleResponse;
			} else {
				$additionalParams = $paymentGateway->addAdditionalParameters($request);
				$res = $paymentGateway->saveTransactionDetails($transactionId, $additionalParams);
				$returnUrl = $res['tenantUrl'];
			}
		} catch (Exception $e) {
			if (!is_null($redirect) && !$redirect) {
				$output = array(
					'status' => 0,
					'details' =>  array('message' => $e->getMessage()),
				);
				$this->getView()->outputMethod = array('Zend_Json', 'encode');
				$this->setOutput(array($output));
				return;
			} else {
				$this->forceRedirectWithMessage($paymentGateway->getReturnUrlOnError(), $e->getMessage(), 'danger');
			}
		}

		if (!is_null($redirect) && !$redirect) {
			$output = array(
				'status' => 1,
				'desc' => 'success',
				'details' => array('credit_card' => $res['creditCard'], 'expiration_date' => $res['expirationDate']),
			);
			$this->setOutput(array($output));
		} else {
			Billrun_Factory::log("Redirecting to: " . $returnUrl, Zend_Log::DEBUG);
			$this->getView()->outputMethod = 'header';
			$this->getView()->output = "Location: " . $returnUrl;
		}
	}

	public function successAction() {
		$this->allowed();
		$this->getView()->outputMethod = 'print_r';
		$this->setOutput(array("SUCCESS", TRUE));
	}

	/**
	 * redirect the user to given url an returns message to present to the user.
	 * 
	 * @param String $redirectUrl - the url to redirect to
	 * @param String $content - the message itself
	 * @param String $type - represent the type of the message (i.e: success, danger, warning...)
	 * @return json structure string which represents the message.
	 */
	protected function forceRedirectWithMessage($redirectUrl, $content, $type) {
		$messageObj = json_encode(array('content' => $content , 'type' => $type));
		$this->forceRedirect($redirectUrl . '&message=' . $messageObj);
	}
	
	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_READ;
	}

}
