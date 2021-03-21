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
class utest_BalanceAddModel extends utest_AbstractUtestModel {

	public function __construct(\UtestController $controller) {
		parent::__construct($controller);
		$this->result = array('balance_before', 'balance_after', 'lines');
		$this->label = 'Balance | Update by pp includes';
	}

	function doTest() {
		$sid = (int) Billrun_Util::filter_var($this->controller->getRequest()->get('sid'), FILTER_VALIDATE_INT);
		$name = Billrun_Util::filter_var($this->controller->getRequest()->get('balanceType'), FILTER_SANITIZE_STRING);
		$operation = Billrun_Util::filter_var($this->controller->getRequest()->get('operation'), FILTER_SANITIZE_STRING);
		$amount = Billrun_Util::filter_var($this->controller->getRequest()->get('amount'), FILTER_SANITIZE_STRING);
		$expiration = Billrun_Util::filter_var($this->controller->getRequest()->get('expiration'), FILTER_SANITIZE_STRING);

		$params = array(
			'sid' => $sid,
			'name' => $name,
			'operation' => $operation,
			'amount' => $amount,
			'expiration' => $expiration
		);

		$data = $this->getRequestData($params);
		$this->controller->sendRequest($data, 'balances');
	}

	/**
	 * Get data for AddBalance request
	 * @param String $type start_call / answer_call / reservation_time / release_call
	 * @param Array $data : imsi
	 * @return XML string
	 */
	protected function getRequestData($params) {
		$amount = (-1) * $params['amount'];
		$query = array("pp_includes_name" => $params['name']);
		$upsert = array(
			"value" => $amount,
			"expiration_date" => date_format(date_create_from_format('d/m/Y H:i', $params['expiration']), 'c')
		);
		
		if(isset($params['operation']) && $params['operation']) {
			$upsert['operation'] = $params['operation'];
		}
		
		$request = array(
			'method' => 'update',
			'sid' => $params['sid'],
			'query' => json_encode($query),
			'upsert' => json_encode($upsert),
			'additional' => json_encode(array(
				'balance_info' => Billrun_Factory::user()->getUsername(),
				'balance_type' => 'MTR',
				'balance_source' => 'UTEST',
			)),
		);
		return $request;
	}

}
