<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Test data model class
 *
 * @package  Models
 * @subpackage uTest
 * @since    4.0
 */
class utest_DataModel extends utest_AbstractUtestModel {

	public function __construct(\UtestController $controller) {
		parent::__construct($controller);
		$this->result = array('balance_before', 'balance_after', 'lines');
		$this->label = 'Data | Real-time event';
	}
	
	protected function getMsccData($usages) {
		$ret = array();
		
		foreach ($usages as $usage) {
			$usedUnitsAndRatingGroup = explode(':', $usage);
			$usedUnits = $usedUnitsAndRatingGroup[0];
			$ratingGroup = isset($usedUnitsAndRatingGroup[1]) ? (int)$usedUnitsAndRatingGroup[1] : 92;
			$currentData = array(
				"event" => "initial",
				"reportingReason" => "0",
				"serviceId" => "400700",
				"ratingGroup" => $ratingGroup,
				"requestedUnits" => 1000,
			);
			if (!empty($usedUnits)) {
				$currentData["usedUnits"] = $usedUnits;
			}
			$ret[] = $currentData;
		}
		
		return $ret;
	}

	/**
	 * main action to do basic tests
	 * 
	 * @return void
	 */
	public function doTest() {
		//Get test params
		$imsi = Billrun_Util::filter_var($this->controller->getRequest()->get('imsi'), FILTER_SANITIZE_STRING);
		$mcc = Billrun_Util::filter_var($this->controller->getRequest()->get('mcc'), FILTER_SANITIZE_STRING);
		$scenarioData = Billrun_Util::filter_var($this->controller->getRequest()->get('scenario'), FILTER_SANITIZE_STRING);
		$scenario = array_map('trim', explode("\n", trim($scenarioData)));

		//Run test scenario
		foreach ($scenario as $index => $name) {
			$nameAndUsages = explode("|", $name);
			$usages = explode(',', $nameAndUsages[1]);
			
			$params = array(
				'imsi' => $imsi,
				'mcc' => $mcc,
				'requestNum' => ($index + 1),
				'type' => $nameAndUsages[0],
				'sessionId' => $this->controller->getReference(),
				'msccData' => $this->getMsccData($usages),
			);
			$data = $this->getRequestData($params);
			$this->controller->sendRequest(array('usaget' => 'data', 'request' => $data));
			sleep(1);
		}
	}

	/**
	 * Get data for DATA request
	 * @param String $type init / update / final
	 * @param Array $data : imsi, requestNum
	 * @return JSON string
	 */
	protected function getRequestData($params) {
		$type = $params['type'];
		$imsi = $params['imsi'];
		$requestNum = $params['requestNum'];
		$sessionId = $params['sessionId'];
		$mcc = $params['mcc'];
		$msccData = $params['msccData'];

		$request = array(
			//"requestType" => "1",
			//"requestNum" => 1,
			"sessionId" => $sessionId,
			"eventTimeStamp" => "20151122", // ??????
			"imsi" => $imsi,
			"imei" => "3542010614744704",
			"msisdn" => "972505050092",
			"msccData" => $msccData,
			"service" => array(
				"PdnConnectionId" => "0",
				"PdpAddress" => "10.161.48.3",
				"CalledStationId" => "test-sacc.labpelephone.net.il",
				"MccMnc" => $mcc,
				"GgsnAddress" => "91.135.99.226",
				"SgsnAddress" => "91.135.96.3",
				"ChargingId" => "0",
				"GPRSNegQoSProfile" => "0",
				"ChargingCharacteristics" => "0800",
				"PDPType" => "0",
				"SGSNMCCMNC" => $mcc,
				"GGSNMCCMNC" => "0",
				"CGAddress" => "0.0.0.0",
				"NSAPI" => "5",
				"SessionStopIndicator" => "0",
				"SelectionMode" => "1",
				"RATType" => array("1"),
				"MSTimeZone" => array("128", "0"),
				"ChargingRuleBaseName" => "0",
				"FilterId" => "0"
			),
		);

		switch ($type) {
			case 'init':
				$request['requestType'] = "1";
				$request['requestNum'] = $requestNum;
				break;
			case 'update':
				$request['requestType'] = "2";
				$request['requestNum'] = $requestNum;
				break;
			case 'final':
				$request['requestType'] = "3";
				$request['requestNum'] = $requestNum;
				break;
			default: return NULL;
				break;
		}

		return json_encode($request);
	}

}
