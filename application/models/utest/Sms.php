<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
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
		$msc = Billrun_Util::filter_var($this->controller->getRequest()->get('msc'), FILTER_SANITIZE_STRING);
		$called_number = Billrun_Util::filter_var($this->controller->getRequest()->get('called_number'), FILTER_SANITIZE_STRING);
		$discount = Billrun_Util::filter_var($this->controller->getRequest()->get('discount'), FILTER_SANITIZE_STRING);
		$usaget = Billrun_Util::filter_var($this->controller->getRequest()->get('usaget'), FILTER_SANITIZE_STRING);
		$send_failed_request = Billrun_Util::filter_var($this->controller->getRequest()->get('send_failed_request'), FILTER_SANITIZE_STRING);

		//Run test scenario
		$params = array(
			'usaget' => $usaget,
			'request' => array(
				'calling_number' => $calling_number,
				'called_number' => $called_number,
				'msc_id' => $msc,
				'pmt_subscriber_type' => 'tmp',
				'discount' => $discount,
				'association_number' => $this->controller->getReference(),
				'transaction_id' => ''
			)
		);

		$data = $this->getRequestData($params);
		$res = $this->controller->sendRequest($data);

		if ($send_failed_request == 'send_failed_request') {
			sleep(1);
			$xml = simplexml_load_string($res);
			$params['request']['transaction_id'] = (string) $xml->transaction_id;
			$data = $this->getRequestData($params);
			$this->controller->sendRequest($data);
		}
	}

	/**
	 * Get data for DATA request
	 * @param String $type init / update / final
	 * @param Array $data : imsi, requestNum
	 * @return JSON string
	 */
	protected function getRequestData($params) {
		$xmlParams = array('rootElement' => 'request');
		$xmlRequest = Billrun_Util::arrayToXml($params['request'], $xmlParams);
		$request = array(
			'request' => $xmlRequest,
			'usaget' => $params['usaget']
		);
		return $request;
	}

}
