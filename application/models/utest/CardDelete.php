<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Update Card utest model class
 *
 * @package  Models
 * @subpackage uTest
 * @since    4.0
 */
class utest_CardDeleteModel extends utest_AbstractUtestModel {

	public function __construct(\UtestController $controller) {
		parent::__construct($controller);
		$this->result = array('cards');
		$this->label = 'Card | Delete';
	}

	function doTest() {
		$query_serial_number = (int) Billrun_Util::filter_var($this->controller->getRequest()->get('serial_number'), FILTER_VALIDATE_INT);
		$query_batch_number = (int) Billrun_Util::filter_var($this->controller->getRequest()->get('batch_number'), FILTER_VALIDATE_INT);
		$query_secret = Billrun_Util::filter_var($this->controller->getRequest()->get('secret'), FILTER_SANITIZE_STRING);
		$query_status = Billrun_Util::filter_var($this->controller->getRequest()->get('status'), FILTER_SANITIZE_STRING);

		$params = array(
			'secret' => $query_secret,
			'serial_number' => $query_serial_number,
			'batch_number' => $query_batch_number,
			'status' => $query_status,
		);

		$data = $this->getRequestData($params);
		$this->controller->sendRequest($data, 'cards');
	}

	/**
	 * Generate data for request data
	 * @param String $type start_call / answer_call / reservation_time / release_call
	 * @param Array $data : imsi
	 * @return XML string
	 */
	protected function getRequestData($params) {
		$query = array();
		foreach ($params as $key => $value) {
			if (!empty($value)) {
				$query[$key] = $value;
			}
		}

		$request = array(
			'method' => 'update',
			'query' => json_encode($query),
		);

		return $request;
	}

}
