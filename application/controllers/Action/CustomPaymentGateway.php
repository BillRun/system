<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Collect.php';

/**
 * Custom payment gateway action class
 *
 * @package  Action
 * @since    5.13
 */
	class CustomPaymentGatewayAction extends ApiAction {
	use Billrun_Traits_Api_UserPermissions;
	
	public function execute() {
		$this->allowed();
		$request = $this->getRequest();
		Billrun_Factory::log()->log('Custom payment gateway API call with params: ' . print_r($request->getRequest(), 1), Zend_Log::INFO);
		$options['cpg_type'] = $request->get('cpg_type');
		$options['gateway_name'] = $request->get('payment_gateway');
		$options['file_type'] = $request->get('file_type');
		$options['params'] = [];
		if (!empty($request->get('parameters'))) {
			$options['params'] = json_decode($request->get('parameters') ,true);
			if (is_null($options['params'])) {
				return $this->setError("Wrong parameters structure, no file was generated");
			}
		}
		$options['params']['created_by'] = Billrun_Factory::user()->getUsername();

		$options['pay_mode'] = !empty($request->get('pay_mode')) ? $request->get('pay_mode') : null;
		if ((in_array($options['file_type'], ["transactions_request"])) && (empty($options['gateway_name']) || empty($options['file_type']))) {
			return $this->setError("Action " . $options['cpg_type'] . " must be transferred with both file type and payment gateway.");
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
		} catch(Exception $ex){
			return $this->setError("Error: " . $ex->getMessage());
        }
		Billrun_Factory::log("Finished " . $options['cpg_type'] . " custom payment gateway request." , Zend_Log::DEBUG);
		$output = array (
			'status' => "Done",
			'details' => array(),
		);
		$this->setSuccess($output, $options);
	}
	
	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_WRITE;
	}
}