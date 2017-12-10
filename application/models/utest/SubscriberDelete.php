<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Create subscriber model class
 *
 * @package  Models
 * @subpackage uTest
 * @since    4.0
 */
class utest_SubscriberDeleteModel extends utest_AbstractUtestModel {

	public function __construct(\UtestController $controller) {
		parent::__construct($controller);
		$this->result = array('subscriber_after', 'subscriber_before', 'balance_before', 'balance_after');
		$this->label = 'Subscriber | Delete';
	}

	public function doTest() {
		$query_sid = (int) Billrun_Util::filter_var($this->controller->getRequest()->get('sid'), FILTER_VALIDATE_INT);
		$query_imsi = Billrun_Util::filter_var($this->controller->getRequest()->get('imsi'), FILTER_SANITIZE_STRING);
		$query_msisdn = Billrun_Util::filter_var($this->controller->getRequest()->get('msisdn'), FILTER_SANITIZE_STRING);

		$keep_balances = (bool) Billrun_Util::filter_var($this->controller->getRequest()->get('keep_balances'), FILTER_SANITIZE_STRING);
		$enable_keep_balances = Billrun_Util::filter_var($this->controller->getRequest()->get('enable-keep_balances'), FILTER_SANITIZE_STRING);

		$params = array(
			'query' => array(
				'sid' => $query_sid,
				'imsi' => $query_imsi,
				'msisdn' => $query_msisdn,
			),
			'keep_balances' => array(
				'value' => $keep_balances,
				'enable' => $enable_keep_balances
			),
		);

		$data = $this->getRequestData($params);
		$this->controller->sendRequest($data, 'subscribers');
	}

	protected function getRequestData($params) {
		$query = array();
		foreach ($params['query'] as $key => $value) {
			if (!empty($value)) {
				$query[$key] = $value;
			}
		}

		$request = array(
			'method' => 'delete',
			'query' => json_encode($query),
		);

		if ($params['keep_balances']['enable'] === 'on') {
			$request['keep_balances'] = $params['keep_balances']['value'];
		}

		return $request;
	}

}
