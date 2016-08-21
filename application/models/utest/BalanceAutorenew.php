<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Add balance model class
 *
 * @package  Models
 * @subpackage uTest
 * @since    4.0
 */
class utest_BalanceAutorenewModel extends utest_AbstractUtestModel {

	public function __construct(\UtestController $controller) {
		parent::__construct($controller);
		$this->result = array('autorenew_before', 'autorenew_after');
		$this->label = 'Balance | Autorenew';
	}

	function doTest() {
		$sid = (int) Billrun_Util::filter_var($this->controller->getRequest()->get('sid'), FILTER_VALIDATE_INT);
		$plan = Billrun_Util::filter_var($this->controller->getRequest()->get('plan'), FILTER_SANITIZE_STRING);
		$from = Billrun_Util::filter_var($this->controller->getRequest()->get('from'), FILTER_SANITIZE_STRING);
		$to = Billrun_Util::filter_var($this->controller->getRequest()->get('to'), FILTER_SANITIZE_STRING);
		$interval = Billrun_Util::filter_var($this->controller->getRequest()->get('interval'), FILTER_SANITIZE_STRING);
		$operation = Billrun_Util::filter_var($this->controller->getRequest()->get('operation'), FILTER_SANITIZE_STRING);

		$params = array(
			'query' => array(
				'sid' => $sid,
				'charging_plan' => $plan,
				'from' => date_format(date_create_from_format('d/m/Y H:i', $from), 'c'),
			),
			'upsert' => array(
				'to' => date_format(date_create_from_format('d/m/Y H:i', $to), 'c'),
				'interval' => $interval,
				'operation' => $operation
			)
		);

		$data = $this->getRequestData($params);
		$this->controller->sendRequest($data, 'subscribersautorenew');
	}

	/**
	 * Get data for request
	 * @param request Data
	 * @return XML string
	 */
	protected function getRequestData($params) {
		$request = array(
			'method' => 'update',
			'query' => json_encode($params['query']),
			'upsert' => json_encode($params['upsert'])
		);
		return $request;
	}

}
