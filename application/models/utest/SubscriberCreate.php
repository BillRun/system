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
class utest_SubscriberCreateModel extends utest_AbstractUtestModel {

	public function __construct(\UtestController $controller) {
		parent::__construct($controller);
		$this->result = array('subscriber_after', 'subscriber_before');
		$this->label = 'Subscriber | Create';
	}

	public function doTest() {
		$sid = (int) Billrun_Util::filter_var($this->controller->getRequest()->get('sid'), FILTER_VALIDATE_INT);
		$aid = (int) Billrun_Util::filter_var($this->controller->getRequest()->get('aid'), FILTER_SANITIZE_STRING);
		$imsi = Billrun_Util::filter_var($this->controller->getRequest()->get('imsi'), FILTER_SANITIZE_STRING);
		$msisdn = Billrun_Util::filter_var($this->controller->getRequest()->get('msisdn'), FILTER_SANITIZE_STRING);
		$plan = Billrun_Util::filter_var($this->controller->getRequest()->get('plan'), FILTER_SANITIZE_STRING);
		$service_provider = Billrun_Util::filter_var($this->controller->getRequest()->get('service_provider'), FILTER_SANITIZE_STRING);
		$connection_type = Billrun_Util::filter_var($this->controller->getRequest()->get('connection_type'), FILTER_SANITIZE_STRING);
		$language = Billrun_Util::filter_var($this->controller->getRequest()->get('language'), FILTER_SANITIZE_STRING);

		$params = array(
			'imsi' => $imsi,
			'msisdn' => $msisdn,
			'aid' => $aid,
			'sid' => $sid,
			'plan' => $plan,
			'service_provider' => $service_provider,
			'charging_type' => $connection_type,
			'language' => $language,
		);

		$data = $this->getRequestData($params);
		$this->controller->sendRequest($data, 'subscribers');
	}

	protected function getRequestData($params) {
		$imsi = array_map('trim', explode("\n", trim($params['imsi'])));
		if (count($imsi) == 1) {
			$imsi = $imsi[0];
		}
		$subscriber = array(
			"imsi" => $imsi,
			"msisdn" => $params['msisdn'],
			"aid" => $params['aid'],
			"sid" => $params['sid'],
			"plan" => $params['plan'],
			"service_provider" => $params['service_provider'],
			"connection_type" => $params['connection_type'],
			"language" => $params['language']
		);
		$request = array(
			'method' => 'create',
			'subscriber' => json_encode($subscriber),
		);
		return $request;
	}

}
