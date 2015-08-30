<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Realtime event action controller class
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       4.0
 */
class RealtimeeventAction extends ApiAction {

	protected $event = null;

	/**
	 * method to execute realtime event
	 */
	public function execute() {
		Billrun_Factory::log("Execute realtime event", Zend_Log::INFO);
		$this->event = $this->getRequestData();
		$this->setEventData();
		$data = $this->process();
		return $this->respond($data);
	}

	/**
	 * make simple sanity to the event input
	 */
	protected function preCheck() {
		
	}

	protected function customer() {
		if (!empty($this->event['imsi'])) {
			
		} else if (!empty($this->event['msisdn'])) {
			
		} else {
			// die no customer identifcation
			return FALSE;
		}
		return TRUE;
	}

	protected function rate() {
		$this->event['arate'] = MongoDBRef::create($collection, $id);
	}

	protected function charge() {
		
	}

	protected function saveEvent() {
		
	}
	
	/**
	 * Gets the data sent to the api
	 * @todo get real data from request (now it's only mock-up)
	 */
	protected function getRequestData() {
		//$requstData = $this->getRequest()->getRequest();
		$jsonData = '{
			"sessionId":"GyOCS.sasnlbumtsma0-0.pelephone.gy.lab;1378620500;536872634",
			"ccRequestType":1,
			"ccRequestNumber":415,
			"destinationHost":"dgu1.pelephone.gy.lab",
			"startTime":"20130908060820",
			"MSCC":{
				"used":1048576,
				"requested":1048576,
				"serviceIdentifier":400700,
				"ratingGroup":92
			},
			"imsi":"425030024380232",
			"msisdn":"972502182928",
			"userEquipment":{
				"type":0,
				"value":"33353833393430343137363030323032"
			},
			"serviceContextId":"32251@3gpp.org",
			"Service-Information":{
				"calledStationId":"sphone.labpelephone.net.il",
				"3GPPChargingCharacteristics":"0400",
				"3GPPPDPType":"IPv4 (0)",
				"3GPPGPRSNegotiatedQoSprofile":"9-13921f73968db044524859",
				"SGSNAddress":"00015b876003",
				"3GPPNSAPI":5,
				"3GPPSelection-Mode":1,
				"CGAddress":"000100000000",
				"GGSNAddressFamily":1,
				"GGSNAddress":"91.135.96.162",
				"3GPPIMSIMCCMNC":42503,
				"3GPPSGSNMCCMNC":42503,
				"3GPPMSTimeZone":2101,
				"PDPAddress":"00010aa12d0",
				"3GPPUserLocationInfo":{
					"geographicLocationType":1,
					"MCC":"425",
					"MNC":"03",
					"locationAreaCode":6101,
					"serviceAreaCode":1046
				},
				"3GPPRATType":"01"
			},
			"recordType":"start_call"
		}';
		
		$xmlData = '<?xml version = "1.0" encoding = "UTF-8"?>
			<request>
			<calling_number>425030024380232</calling_number>
			<imsi>425030024380232</imsi>
			<dialed_digits>425030024380232</dialed_digits>
			<event_type></event_type>
			<service_key> </service_key>
			<call_reference>2</call_reference>
			<call_id> </call_id>
			<vlr_number> </vlr_number>
			<location_information>
				<mcc>123</mcc>
				<mnc>45</mnc>
				<lac></lac>
				<ci></ci>
			</location_information>
			<duration>2</duration>

			<time_date>2013/09/01 11:59:03</time_date>
			<time_zone>0</time_zone>
			<free_call>0</free_call>
			<recordType>reservation_time</recordType>
			<SGSNAddress>00015b876003</SGSNAddress>
			</request>
		';
		
		$decoder = Billrun_Decoder_Manager::getDecoder(array(
			'controllerName' => $this->getRequest()->controller, 
			'actionName' => $this->getRequest()->getActionName()
		));
		if (!$decoder) {
			Billrun_Factory::log('Cannot get decoder', Zend_Log::ALERT);
			return false;
		}
		
		return Billrun_Util::parseDataToBillrunConvention($decoder->decode($xmlData));
	}
	
	/**
	 * Sets the data of $this->event
	 */
	protected function setEventData() {
		$this->event['source'] = 'realtime';
		$this->event['type'] = 'gy';
		$this->event['rand'] = rand(1,1000000);
		$this->event['stamp'] = Billrun_Util::generateArrayStamp($this->event);
		if (isset($this->event['service-information']['sgsnaddress'])) {
			$this->event['sgsn_address'] = long2ip(hexdec($this->event['service-information']['sgsnaddress']));
		} else {
			$sgsn_dec = hexdec($this->event['sgsnaddress']);
			if (is_numeric($sgsn_dec)) {
				$this->event['sgsn_address'] = long2ip($sgsn_dec);
			}
		}
		
		if (isset($this->event['sgsnaddress'])) {
			$this->event['ggsn_address'] = $this->event['sgsnaddress'];
			unset($this->event['sgsnaddress']);
		}

		if (isset($this->event['start_time'])) {
			$this->event['record_opening_time'] = $this->event['startTime'];
			unset($this->event['start_time']);
		}
		
		if (isset($this->event['time_date'])) {
			$this->event['record_opening_time'] = $this->event['time_date'];
		}
		
		$this->event['billrun_prepend'] = $this->isPrepend();
		$this->event['urt'] = new MongoDate(strtotime($this->event['record_opening_time']));
	}
	
	/**
	 * Runs Billrun process
	 * 
	 * @return type Data generated by process
	 */
	protected function process() {
		$options = array(
			'type' => 'Realtime',
			'parser' => 'none',
		);
		$processor = Billrun_Processor::getInstance($options);
		$processor->addDataRow($this->event);
		$processor->process();

		/*$ret = array(
			'sessionId' => $this->event['sessionId'],
			'ccRequestType' => $this->event['ccRequestType'],
			'ccRequestNumber' => $this->event['ccRequestNumber'],
			'MSCC' => $this->event['MSCC']
//			'MSCC' => array(
//				'used' => 1048576,
//				'requested' => 1048576,
//				'granted' => 1048576,
//				'serviceIdentifier' => 400700,
//				'ratingGroup' => 92,
//				'returnCode' => 0
//			),
		);
		$ret['MSCC']['returnCode'] = $data['granted_return_code'];
		if ($data['granted_return_code'] == Billrun_Factory::config()->getConfigValue('prepaid.ok')) {
			$ret['MSCC']['granted'] = $data['usagev'];
		}*/
		return $processor->getData()['data'][0];
	}
	
	/**
	 * Send respond
	 * 
	 * @param type $data
	 * @return boolean
	 */
	protected function respond($data) {
		$encoder = Billrun_Encoder_Manager::getEncoder(array(
			'controllerName' => $this->getRequest()->controller, 
			'actionName' => $this->getRequest()->getActionName()
			));
		if (!$encoder) {
			Billrun_Factory::log('Cannot get encoder', Zend_Log::ALERT);
			return false;
		}
		
		$responder = Billrun_ActionManagers_Realtime_Responder_Call_Manager::getResponder($data);
		if (!$responder) {
			Billrun_Factory::log('Cannot get responder', Zend_Log::ALERT);
			return false;
		}

		$response = array($encoder->encode($responder->getResponse(), "response"));
		$this->getController()->setOutput($response);
		// Sends response
		$responseUrl = Billrun_Factory::config()->getConfigValue('IN.respose.url.realtimeevent');
		return Billrun_Util::sendRequest($responseUrl, $response);
	}
	
	/**
	 * Checks if the row should really decrease balance from the subscriber's balance, or just prepend
	 * 
	 * @return boolean
	 */
	protected function isPrepend() {
		return ($this->event['record_type'] === 'start_call');
	}

}
