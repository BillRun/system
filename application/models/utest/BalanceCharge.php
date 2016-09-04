<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * TestAddChargeModel  model class
 *
 * @package  Models
 * @subpackage uTest
 * @since    4.0
 */
class utest_BalanceChargeModel extends utest_AbstractUtestModel {

	public function __construct(\UtestController $controller) {
		parent::__construct($controller);
		$this->result = array('balance_before', 'balance_after', 'lines');
		$this->label = 'Balance | Update by charging plan';
	}

	function doTest() {
		$sid = (int) Billrun_Util::filter_var($this->controller->getRequest()->get('sid'), FILTER_VALIDATE_INT);
		$name = Billrun_Util::filter_var($this->controller->getRequest()->get('balanceType'), FILTER_SANITIZE_STRING);
		$operation = Billrun_Util::filter_var($this->controller->getRequest()->get('operation'), FILTER_SANITIZE_STRING);

		$params = array(
			'sid' => $sid,
			'name' => $name,
			'operation' => $operation
		);

		$data = $this->getRequestData($params);
		$this->controller->sendRequest($data, 'balances');
	}

	/**
	 * Get data for AddCharge request
	 * @param String $type start_call / answer_call / reservation_time / release_call
	 * @param Array $data : imsi
	 * @return XML string
	 */
	protected function getRequestData($params) {
		$upsert = array("a" => 1);
		if(isset($params['operation']) && $params['operation']) {
			$upsert['operation'] = $params['operation'];
		}
		
		$request = array(
			'method' => 'update',
			'sid' => $params['sid'],
			'query' => json_encode(["charging_plan_name" => $params['name']]),
			'upsert' => json_encode($upsert),
			'additional' => json_encode(array(
				'balance_info' => Billrun_Factory::user()->getUsername(),
				'balance_type' => 'RECHARGE',
				'balance_source' => 'UTEST',
			)),
		);
		return $request;
	}

}
