<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
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
		$this->label = 'Update balance by customer plan';
	}
	
	function doTest() {
		$sid = (int) Billrun_Util::filter_var($this->controller->getRequest()->get('sid'), FILTER_VALIDATE_INT);
		$name = Billrun_Util::filter_var($this->controller->getRequest()->get('balanceType'), FILTER_SANITIZE_STRING);
		$amount = (int) Billrun_Util::filter_var($this->controller->getRequest()->get('amount'), FILTER_SANITIZE_NUMBER_INT);

		$params = array(
			'sid' => $sid,
			'name' => $name,
			'amount' => $amount
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
		$request = array(
			'method' => 'update',
			'sid' => $params['sid'],
			'query' => json_encode(["pp_includes_name" => $params['name']]),
			'upsert' => json_encode(["value" => $amount, "expiration_date" => "2016-07-01T00:00:00+02:00"])
		);
		return $request;
	}

}
