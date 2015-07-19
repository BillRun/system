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
		Billrun_Factory::log()->log("Execute realtime event", Zend_Log::INFO);
//		$this->event = $this->getRequest()->getRequest();
		$a = '{
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
			}
		}';
		$this->event = @json_decode($a, JSON_OBJECT_AS_ARRAY);
		$this->event['source'] = 'realtime';
		$this->event['type'] = 'gy';
		
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
		
		$this->event['urt'] = new MongoDate(strtotime($this->event['record_opening_time']));

		$options = array(
			'type' => 'Realtime',
			'parser' => 'none',
		);
		$processor = Billrun_Processor::getInstance($options);
		$processor->addDataRow($this->event);
		$processor->process();

		$ret = array(
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
		$ret['MSCC']['granted'] = $ret['MSCC']['requested'];
		$ret['MSCC']['returnCode'] = 0;
		$this->getController()->setOutput(array($ret));
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

}
