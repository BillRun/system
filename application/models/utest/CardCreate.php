<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Create Card utest model class
 *
 * @package  Models
 * @subpackage uTest
 * @since    4.0
 */
class utest_CardCreateModel extends utest_AbstractUtestModel {

	public function __construct(\UtestController $controller) {
		parent::__construct($controller);
		$this->result = array('cards');
		$this->label = 'Card | Create';
	}

	function doTest() {
		$secrets = Billrun_Util::filter_var($this->controller->getRequest()->get('secrets'), FILTER_SANITIZE_STRING);
		$balanceType = Billrun_Util::filter_var($this->controller->getRequest()->get('balanceType'), FILTER_SANITIZE_STRING);
		$status = Billrun_Util::filter_var($this->controller->getRequest()->get('status'), FILTER_SANITIZE_STRING);
		$created = Billrun_Util::filter_var($this->controller->getRequest()->get('created'), FILTER_SANITIZE_STRING);
		$expiration = Billrun_Util::filter_var($this->controller->getRequest()->get('expiration'), FILTER_SANITIZE_STRING);

		$override_service_provider = Billrun_Util::filter_var($this->controller->getRequest()->get('override_service_provider'), FILTER_SANITIZE_STRING);
		$service_provider = Billrun_Util::filter_var($this->controller->getRequest()->get('service_provider'), FILTER_SANITIZE_STRING);

		$params = array(
			'secrets' => $secrets,
			'balance_type' => $balanceType,
			'to' => $expiration,
			'creation_time' => $created,
			'status' => $status,
			'override_service_provider' => $override_service_provider,
			'service_provider' => $service_provider
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
		$cards = array();

		$secrets = array_map('trim', explode("\n", trim($params['secrets'])));

		$planAndProvider = explode("|", $params['balance_type']);
		$charging_plan_name = $planAndProvider[0];
		$service_provider = ($params['override_service_provider'] == 'on') ? $params['service_provider'] : $planAndProvider[1];

		foreach ($secrets as $key => $secret) {
			$cards[] = array(
				'secret' => $secret,
				'batch_number' => $this->controller->getReference(),
				'serial_number' => (int) ($this->controller->getReference() . $key),
				'charging_plan_name' => $charging_plan_name,
				'service_provider' => $service_provider,
				'to' => date_format(date_create_from_format('d/m/Y H:i', $params['to']), 'c'),
				'creation_time' => date_format(date_create_from_format('d/m/Y H:i', $params['creation_time']), 'c'),
				'status' => $params['status'],
			);
		}

		$request = array(
			'method' => 'create',
			'cards' => json_encode($cards),
		);

		return $request;
	}

}
