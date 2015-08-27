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
//		$this->event = $this->getRequest()->getRequest();
//		db.subscribers.insert({"from":ISODate("2012-01-01 "),"to":ISODate("2099-01-01 00:00:00"),imsi:"425030024380232", msisdn:"9725050500", aid:12345, sid:77777, plan:"LARGE"})
//		> db.plans.findOne({name:"LARGE"})
//{
//	"_id" : ObjectId("51bd8dc9eb2f76d2178dd3dd"),
//	"from" : ISODate("2012-01-01T00:00:00Z"),
//	"to" : ISODate("3013-01-01T00:00:00Z"),
//	"name" : "LARGE",
//	"include" : {
//		"call" : "UNLIMITED",
//		"sms" : "UNLIMITED",
//		"mms" : "UNLIMITED",
//		"data" : "UNLIMITED",
//		"groups" : {
//			"IRD" : {
//				"data" : "UNLIMITED",
//				"limits" : {
//					"flag" : "plugin"
//				}
//			}
//		}
//	},
//	"price" : 83.898305085,
//	"vatable" : true
//}
//> 
//		> db.rates.find({key:/INTERNET_BILL_BY_V/}).pretty()
//{
//	"_id" : ObjectId("521e07fcd88db0e73f000200"),
//	"from" : ISODate("2012-06-01T00:00:00Z"),
//	"to" : ISODate("2113-08-28T18:23:55Z"),
//	"key" : "INTERNET_BILL_BY_VOLUME",
//	"params" : {
//		"sgsn_addresses" : "/^(?=91.135.)/"
//	},
//	"rates" : {
//		"data" : {
//			"category" : "base",
//			"rate" : [
//				{
//					"to" : NumberLong(2147483647),
//					"price" : 7.27378716e-8,
//					"interval" : NumberLong(1)
//				}
//			],
//			"unit" : "bytes",
//			"plans" : [
//				DBRef("plans", ObjectId("51bd8dc9eb2f76d2178dd3de")),
//				DBRef("plans", ObjectId("51bd8dc9eb2f76d2178dd3dd")),
//				DBRef("plans", ObjectId("53349c0c79c7f054396fcd75")),
//				DBRef("plans", ObjectId("5396d8098f7ac3710d6228ce")),
//				DBRef("plans", ObjectId("5396d8448f7ac326986228ce")),
//				DBRef("plans", ObjectId("53b1730e8f7ac39233da4d5a")),
//				DBRef("plans", ObjectId("54114b0fd88db0d336b22964")),
//				DBRef("plans", ObjectId("547ed64d8f7ac3f2967560bc")),
//				DBRef("plans", ObjectId("54b6be8a8f7ac3e15bd53d76"))
//			]
//		}
//	}
//}



		/*$a = '{
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
		}';*/
		$a = '<?xml version = "1.0" encoding = "UTF-8"?>
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
			<free_call>false</free_call>
			<recordType>start_call</recordType>
			<SGSNAddress>00015b876003</SGSNAddress>
			</request>
		';
		$decoder = Billrun_Decoder_Manager::getDecoder($this->getRequest()->controller, $this->getRequest()->getActionName());
		if (!$decoder) {
			Billrun_Factory::log('Cannot get decoder', Zend_Log::ALERT);
			return false;
		}
		
		$this->event = $decoder->decode($a);
		$this->event['source'] = 'realtime';
		$this->event['type'] = 'gy';
		$this->event['rand'] = rand(1,1000000);
		$this->event['stamp'] = Billrun_Util::generateArrayStamp($this->event);
		if (isset($this->event['Service-Information']['SGSNAddress'])) {
			$this->event['sgsn_address'] = long2ip(hexdec($this->event['Service-Information']['SGSNAddress']));
		} else {
			$sgsn_dec = hexdec($this->event['SGSNAddress']);
			if (is_numeric($sgsn_dec)) {
				$this->event['sgsn_address'] = long2ip($sgsn_dec);
			}
		}
		
		if (isset($this->event['GGSNAddress'])) {
			$this->event['ggsn_address'] = $this->event['GGSNAddress'];
			unset($this->event['GGSNAddress']);
		}

		if (isset($this->event['startTime'])) {
			$this->event['record_opening_time'] = $this->event['startTime'];
			unset($this->event['startTime']);
		}
		
		if (isset($this->event['time_date'])) {
			$this->event['record_opening_time'] = $this->event['time_date'];
		}
		
		if (isset($this->event['recordType'])) {
			$this->event['record_type'] = $this->event['recordType'];
			unset($this->event['recordType']);
		}
		
		$this->event['billrun_prepend'] = $this->isPrepend();
		$this->event['urt'] = new MongoDate(strtotime($this->event['record_opening_time']));

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
		);*/
		$data = $processor->getData()['data'][0];
		/*$ret['MSCC']['returnCode'] = $data['grantedReturnCode'];
		if ($data['grantedReturnCode'] == Billrun_Factory::config()->getConfigValue('prepaid.ok')) {
			$ret['MSCC']['granted'] = $data['usagev'];
		}
		$this->getController()->setOutput(array($ret));*/
		
		return $this->respond($data);
		//$this->getController()->setOutput($ret);
		
//		if ($this->customer() !== TRUE) {
//			die("error on customer");
//		}
//		if ($this->rate() !== TRUE) {
//			die("error on customer");
//		}
//		if ($this->charge() !== TRUE) {
//			die("error on customer");
//		}
//		if ($this->saveEvent() !== TRUE) {
//			die("error on customer");
//		}
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
	
	protected function respond($data) {
		$encoder = Billrun_Encoder_Manager::getEncoder($this->getRequest()->controller, $this->getRequest()->getActionName());
		if (!$encoder) {
			Billrun_Factory::log('Cannot get encoder', Zend_Log::ALERT);
			return false;
		}
		
		$responder = Billrun_ActionManagers_Realtime_Responder_Call_Manager::getResponder($data);
		if (!$responder) {
			Billrun_Factory::log('Cannot get responder', Zend_Log::ALERT);
			return false;
		}

		$response = array($encoder->encode($responder->getResponse()));
		$this->getController()->setOutput($response);
		//TODO: send response
		return true;
	}
	
	protected function isPrepend() {
		return ($this->event['record_type'] === 'start_call');
	}
	
	protected function recordTypeToClassName($recordType) {
		$classNamePref = 'Billrun_ActionManagers_Realtime_Responder_Call_';
		return $classNamePref . str_replace(" ","", ucwords(str_replace("_", " ", $recordType)));
	}

}
