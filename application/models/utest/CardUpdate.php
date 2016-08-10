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
class utest_CardUpdateModel extends utest_AbstractUtestModel {

	public function __construct(\UtestController $controller) {
		parent::__construct($controller);
		$this->result = array('cards');
		$this->label = 'Card | Update';
	}

	function doTest() {
		$query_serial_number = (int) Billrun_Util::filter_var($this->controller->getRequest()->get('serial_number'), FILTER_VALIDATE_INT);
		$query_batch_number = (int) Billrun_Util::filter_var($this->controller->getRequest()->get('batch_number'), FILTER_VALIDATE_INT);
		$query_secret = Billrun_Util::filter_var($this->controller->getRequest()->get('secret'), FILTER_SANITIZE_STRING);
		$query_status = Billrun_Util::filter_var($this->controller->getRequest()->get('status'), FILTER_SANITIZE_STRING);


		$new_status = Billrun_Util::filter_var($this->controller->getRequest()->get('new_status'), FILTER_SANITIZE_STRING);
		$send_new_status = Billrun_Util::filter_var($this->controller->getRequest()->get('send_new_status'), FILTER_SANITIZE_STRING);

		$new_batch_number = (int) Billrun_Util::filter_var($this->controller->getRequest()->get('new_batch_number'), FILTER_VALIDATE_INT);
		$send_new_batch_number = Billrun_Util::filter_var($this->controller->getRequest()->get('send_new_batch_number'), FILTER_SANITIZE_STRING);

		$new_serial_number = (int) Billrun_Util::filter_var($this->controller->getRequest()->get('new_serial_number'), FILTER_SANITIZE_STRING);
		$send_new_serial_number = Billrun_Util::filter_var($this->controller->getRequest()->get('send_new_serial_number'), FILTER_SANITIZE_STRING);

		$new_service_provider = Billrun_Util::filter_var($this->controller->getRequest()->get('new_service_provider'), FILTER_SANITIZE_STRING);
		$send_new_service_provider = Billrun_Util::filter_var($this->controller->getRequest()->get('send_new_service_provider'), FILTER_SANITIZE_STRING);

		$new_expiration = Billrun_Util::filter_var($this->controller->getRequest()->get('new_expiration'), FILTER_SANITIZE_STRING);
		$send_new_expiration = Billrun_Util::filter_var($this->controller->getRequest()->get('send_new_expiration'), FILTER_SANITIZE_STRING);

		$new_paln = Billrun_Util::filter_var($this->controller->getRequest()->get('new_paln'), FILTER_SANITIZE_STRING);
		$send_new_paln = Billrun_Util::filter_var($this->controller->getRequest()->get('send_new_paln'), FILTER_SANITIZE_STRING);

		$params = array(
			'query' => array(
				'secret' => $query_secret,
				'serial_number' => $query_serial_number,
				'batch_number' => $query_batch_number,
				'status' => $query_status,
			),
			'update' => array(
				'status' => array(
					'enable' => $send_new_status,
					'value' => $new_status
				),
				'batch_number' => array(
					'value' => $new_batch_number,
					'enable' => $send_new_batch_number
				),
				'serial_number' => array(
					'value' => $new_serial_number,
					'enable' => $send_new_serial_number
				),
				'to' => array(
					'value' => date_format(date_create_from_format('d/m/Y H:i', $new_expiration), 'c'),
					'enable' => $send_new_expiration
				),
				'charging_plan_name' => array(
					'value' => $new_paln,
					'enable' => $send_new_paln
				),
				'service_provider' => array(
					'value' => $new_service_provider,
					'enable' => $send_new_service_provider
				),
			),
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
		foreach ($params['query'] as $key => $value) {
			if (!empty($value)) {
				$query[$key] = $value;
			}
		}

		$update = array();
		foreach ($params['update'] as $key => $param) {
			if ($param['enable'] === 'on') {
				$update[$key] = $param['value'];
			}
		}

		$request = array(
			'method' => 'update',
			'query' => json_encode($query),
			'update' => json_encode($update),
		);

		return $request;
	}

}
