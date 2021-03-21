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
class utest_BalanceCardModel extends utest_AbstractUtestModel {

	public function __construct(\UtestController $controller) {
		parent::__construct($controller);
		$this->result = array('balance_before', 'balance_after', 'lines');
		$this->label = 'Balance | Update by card';
	}

	function doTest() {
		$sid = (int) Billrun_Util::filter_var($this->controller->getRequest()->get('sid'), FILTER_VALIDATE_INT);
		$secret = Billrun_Util::filter_var($this->controller->getRequest()->get('secret'), FILTER_SANITIZE_STRING);

		$params = array(
			'sid' => $sid,
			'secret' => $secret,
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
		$request = array(
			'method' => 'update',
			'sid' => $params['sid'],
			'query' => json_encode(["secret" => $params['secret']]),
			'additional' => json_encode(array(
				'balance_info' => Billrun_Factory::user()->getUsername(),
				'balance_type' => 'RECHCARD',
				'balance_source' => 'UTEST',
			)),
		);
		return $request;
	}

}
