<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Test data model class
 *
 * @package  Models
 * @subpackage uTest
 * @since    4.0
 */
class utest_SmsModel extends utest_AbstractUtestModel {

	public function __construct(\UtestController $controller) {
		parent::__construct($controller);
		$this->result = array('balance_before', 'balance_after', 'lines');
		$this->label = 'SMS/MMS | Real-time event';
	}
	
	/**
	 * main action to do basic tests
	 * 
	 * @return void
	 */
	public function doTest() {
		//Get test params
		$calling_number = Billrun_Util::filter_var($this->controller->getRequest()->get('msisdn'), FILTER_SANITIZE_STRING);
		$called_number = Billrun_Util::filter_var($this->controller->getRequest()->get('called_number'), FILTER_SANITIZE_STRING);
		$source_system = Billrun_Util::filter_var($this->controller->getRequest()->get('source_system'), FILTER_SANITIZE_STRING);
		$usaget = Billrun_Util::filter_var($this->controller->getRequest()->get('usaget'), FILTER_SANITIZE_STRING);

		//Run test scenario
		$params = array(
			'calling_number' => $calling_number,
			'called_number' => $called_number,
			'source_system' => $source_system,
			'usaget' => $usaget,
		);
		
		$data = $this->getRequestData($params);
		$this->controller->sendRequest($data);
	}

	/**
	 * Get data for DATA request
	 * @param String $type init / update / final
	 * @param Array $data : imsi, requestNum
	 * @return JSON string
	 */
	protected function getRequestData($params) {

		$request = array(
			'request' => json_encode($params),
			'usaget' => $params['usaget']
		);

		return $request;
	}

}
