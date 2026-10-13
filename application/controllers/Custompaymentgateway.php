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
			$system_params = ['aids', 'invoices', 'exclude_accounts', 'billrun_key', 'min_invoice_date', 'mode', 'pay_mode'];
			//Ignoring input empty system fields
			$options['params'] = array_filter(
				$options['params'],
				function ($value, $key) use ($system_params) {
					return !(in_array($key, $system_params, true) && $value === "");
				},
				ARRAY_FILTER_USE_BOTH
			);
		}
		$options['params']['created_by'] = Billrun_Factory::user() ? Billrun_Factory::user()->getUsername() : null;

		$options['pay_mode'] = !empty($request->get('pay_mode')) ? $request->get('pay_mode') : null;
		if (($res1 = $this->validateOptions($options)) !== true) {
			return $this->setError("Input validation didn't pass for custom params - " . $res1);
		}
		if ($res2 = Billrun_Bill_Payment::validateChargeFilters($options['params'])) {
			return $this->setError("Input validation didn't pass for system params - " . $res2);
		}
		$cmd = 'php ' . APPLICATION_PATH . '/public/index.php ' . Billrun_Util::getCmdEnvParams() . ' --generate --type ' . $options['cpg_type'] . ' payment_gateway=' . $options['gateway_name'] . ' file_type=' . $options['file_type'];
		if (!is_null($options['pay_mode'])) {
			$cmd .= " pay_mode=" . $options['pay_mode'];
		}
		foreach ($options['params'] as $name => $value) {
			$cmd .= " " . $name . "=" . (is_array($value) ? implode(",", $value) : $value);
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
		$validation_excluded_params = ['aids', 'invoices', 'exclude_accounts', 'billrun_key', 'min_invoice_date', 'mode', 'pay_mode'];
		if (isset($options['pay_mode']) && !in_array($options['pay_mode'], ['one_payment', 'multiple_payments'])) {
			return "pay_mode parameter's value isn't valid";
		}

		$paymentsGatewaysConfig = Billrun_Factory::config()->getConfigValue('payment_gateways', []);
		if (empty($paymentsGatewaysConfig)) {
			return "Payment gateways configuration is empty";
		} else {
			$gatewaysOptions = array_column($paymentsGatewaysConfig, 'name');
			if (!in_array($options['gateway_name'], $gatewaysOptions)) {
				return "gateway_name parameter's value isn't valid";
			}
		}
		if (!empty($options['params'])) {
			$email_reg = "/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix";
			$general_reg = "/^[\w\d_-]+$/";
			foreach ($options['params'] as $name => $value) {
				if (!in_array($name, $validation_excluded_params)) {
					if (preg_match($email_reg, $value)) {
						continue;
					} elseif (!preg_match($general_reg, $value)) {
						return $name;
					}
				}
			}
		}

		return true;
	}

	protected function render($tpl, array $parameters = null) {
		return parent::render('index', $parameters);
	}

}
