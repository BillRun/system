<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class manages custom payment gateway actions.
 *
 * @package     Controllers
 * @subpackage  Action
 *
 */
class CustompaymentgatewayController extends ApiController {

	use Billrun_Traits_Api_UserPermissions;

	public function generateTransactionsRequestFileAction() {
		$this->allowed();
		$request = $this->getRequest();
		Billrun_Factory::log()->log('Custom payment gateway API call with params: ' . print_r($request->getRequest(), 1), Zend_Log::INFO);
		$options['gateway_name'] = $request->get('payment_gateway');
		$options['file_type'] = $request->get('file_type');
		$options['cpg_type'] = "transactions_request";
		$options['params'] = [];
		if (!empty($request->get('parameters'))) {
			$options['params'] = json_decode($request->get('parameters'), true);
			if (is_null($options['params'])) {
				return $this->setError("Wrong parameters structure, no file was generated");
			}
		}
		$options['params']['created_by'] = Billrun_Factory::user() ? Billrun_Factory::user()->getUsername() : null;

		$options['pay_mode'] = !empty($request->get('pay_mode')) ? $request->get('pay_mode') : null;

		if (!$this->validateOptions($options)) {
			return $this->setError("One or more of the input parameters are not valid. ");
		}
		$cmd = 'php ' . APPLICATION_PATH . '/public/index.php ' . Billrun_Util::getCmdEnvParams() . ' --generate --type ' . $options['cpg_type'] . ' payment_gateway=' . $options['gateway_name'] . ' file_type=' . $options['file_type'];
		if (!is_null($options['pay_mode'])) {
			$cmd .= " pay_mode=" . $options['pay_mode'];
		}
		foreach ($options['params'] as $name => $value) {
			$cmd .= " " . $name . "=" . $value;
		}
		try {
			$success = Billrun_Util::forkProcessCli($cmd);
		} catch (Exception $ex) {
			return $this->setError("Error: " . $ex->getMessage());
		}
		Billrun_Factory::log("Finished " . $options['cpg_type'] . " custom payment gateway request.", Zend_Log::DEBUG);
		$output = array(
			'status' => "Done",
			'details' => array(),
		);
		$this->setSuccess($output, $options);
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_WRITE;
	}

	protected function validateOptions($options) {
		if (isset($options['pay_mode']) && !in_array($options['pay_mode'], ['one_payment', 'multiple_payments'])) {
			Billrun_Factory::log("pay_mode parameter's value isn't valid", Zend_Log::ERR);
			return false;
		}

		$paymentsGatewaysConfig = Billrun_Factory::config()->getConfigValue('payment_gateways', []);
		if (empty($paymentsGatewaysConfig)) {
			Billrun_Factory::log("Payment gateways configuration is empty", Zend_Log::ERR);
			return false;
		} else {
			$gatewaysOptions = array_column($paymentsGatewaysConfig, 'name');
			if (!in_array($options['gateway_name'], $gatewaysOptions)) {
				Billrun_Factory::log("gateway_name parameter's value isn't valid", Zend_Log::ERR);
				return false;
			}
		}
		if (!empty($options['params'])) {
			if (!preg_match('/^[\w\d_-]+$/', implode("", $options['params']))) {
				return false;
			}
		}

		return true;
	}

	protected function render($tpl, array $parameters = null) {
		return parent::render('index', $parameters);
	}

}
