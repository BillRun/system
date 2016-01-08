<?php

class AddChargeModel extends UtestModel {

	function doTest() {
		$sid = (int) Billrun_Util::filter_var($this->controller->getRequest()->get('sid'), FILTER_VALIDATE_INT);
		$name = Billrun_Util::filter_var($this->controller->getRequest()->get('balanceType'), FILTER_SANITIZE_STRING);

		$params = array(
			'sid' => $sid,
			'name' => $name
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
		$request = array(
			'method' => 'update',
			'sid' => $params['sid'],
			'query' => json_encode(["charging_plan_name" => $params['name']]),
			'upsert' => json_encode(["a" => 1])
		);
		return $request;
	}

}
