<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Events model class
 *
 * @package  Models
 * @subpackage uTest
 * @since    0.4
 */
class CreateSubscriberModel extends UtestModel {

	public function doTest() {
		$sid = (int) Billrun_Util::filter_var($this->controller->getRequest()->get('sid'), FILTER_VALIDATE_INT);
		$aid = (int) Billrun_Util::filter_var($this->controller->getRequest()->get('aid'), FILTER_SANITIZE_STRING);
		$imsi = Billrun_Util::filter_var($this->controller->getRequest()->get('imsi'), FILTER_SANITIZE_STRING);
		$msisdn = Billrun_Util::filter_var($this->controller->getRequest()->get('msisdn'), FILTER_SANITIZE_STRING);
		$plan = Billrun_Util::filter_var($this->controller->getRequest()->get('plan'), FILTER_SANITIZE_STRING);
		$service_provider = Billrun_Util::filter_var($this->controller->getRequest()->get('service_provider'), FILTER_SANITIZE_STRING);
		$charging_type = Billrun_Util::filter_var($this->controller->getRequest()->get('charging_type'), FILTER_SANITIZE_STRING);
		$language = Billrun_Util::filter_var($this->controller->getRequest()->get('language'), FILTER_SANITIZE_STRING);

		$params = array(
			'imsi' => $imsi,
			'msisdn' => $msisdn,
			'aid' => $aid,
			'sid' => $sid,
			'plan' => $plan,
			'service_provider' => $service_provider,
			'charging_type' => $charging_type,
			'language' => $language,
		);

		$data = $this->getRequestData($params);
		$this->controller->sendRequest($data, 'subscribers');
	}

	protected function getRequestData($params) {
		$imsi = array_map('trim', explode("\n", trim($params['imsi'])));
		if(count($imsi) == 1) {
			$imsi = $imsi[0];
		}
		$subscriber = array(
			"imsi" => $imsi,
			"msisdn" => $params['msisdn'],
			"aid" => $params['aid'],
			"sid" => $params['sid'],
			"plan" => $params['plan'],
			"service_provider" => $params['service_provider'],
			"charging_type" => $params['charging_type'],
			"language" => $params['language']
		);
		$request = array(
			'method' => 'create',
			'subscriber' => json_encode($subscriber),
		);
		return $request;
	}

}
