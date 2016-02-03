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
class utest_ServiceModel extends utest_AbstractUtestModel {

	public function __construct(\UtestController $controller) {
		parent::__construct($controller);
		$this->result = array('balance_before', 'balance_after', 'lines');
		$this->label = 'Service | Real-time event';
	}
	
	/**
	 * main action to do basic tests
	 * 
	 * @return void
	 */
	public function doTest() {
		//Get test params
		$calling_number = Billrun_Util::filter_var($this->controller->getRequest()->get('msisdn'), FILTER_SANITIZE_STRING);
		$service_name = Billrun_Util::filter_var($this->controller->getRequest()->get('service_name'), FILTER_SANITIZE_STRING);

		//Run test scenario
		$params = array(
			'calling_number' => $calling_number,
			'service_name' => $service_name,
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
			'request' => $this->array2xml($params, 'request'),
			'usaget' => 'service'
		);

		return $request;
	}

}
