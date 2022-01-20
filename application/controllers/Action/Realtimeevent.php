<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
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
	protected $usaget = null;
	protected $events = null;

	/**
	 * method to execute realtime event
	 */
	public function execute() {
		$this->events = array();
		Billrun_Factory::log("Execute realtime event", Zend_Log::INFO);
		$this->event = $this->getRequestData();
		if ($this->getRequest()->get('mode', 'sync') == 'async') {
			unset($this->event['mode']);
			$asyncData = array(
				'mode' => 'sync',
				'usaget' => $this->usaget,
				'request' => $this->getRealtimeRequestBody(),
			);
			Billrun_Util::forkProcessWeb('api/realtimeevent', $asyncData);
			$this->event['granted_return_code'] = Billrun_Factory::config()->getConfigValue('prepaid.ok');
			return $this->respond($this->event);
		}

		$this->setEventData();
		if (isset($this->event['reverse_charge']) && $this->event['reverse_charge']) {
			return $this->forward("reversecharge", array("event" => $this->event, "usaget" => $this->usaget));
		}
		if (isset($this->event['api_name']) && $this->event['api_name'] === 'healthcheck_in') {
			return $this->forward('healthcheck', array("event" => $this->event, "usaget" => $this->usaget));
		}
		// split event mscc array into seperated events 
		$this->setEventDataEvents();
		
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
		$request = $this->getRequest()->getRequest();
		$this->usaget = $request['usaget'];
		$decoder = Billrun_Decoder_Manager::getDecoder(array(
				'usaget' => $this->usaget
		));
		if (!$decoder) {
			Billrun_Factory::log('Cannot get decoder', Zend_Log::ALERT);
			return false;
		}
		
		$requestBody = $this->getRealtimeRequestBody();

		return Billrun_Util::parseDataToBillrunConvention($decoder->decode($requestBody));
	}
	
	/**
	 * method to get the realtime request information
	 * the $_REQUEST is checked and if empty it will take it from PHP input
	 * 
	 * @return string
	 */
	protected function getRealtimeRequestBody() {
		$request = $this->getRequest()->getRequest();
		if (empty($request['request'])) {
			return file_get_contents("PHP://input");
		}
		return $request['request'];
	}

	/**
	 * Sets the data of $this->event
	 */
	protected function setEventData() {
		$this->event['source'] = 'realtime';
		$this->event['type'] = $this->getEventType();
		$this->event['rand'] = rand(1, 1000000);
		$this->event['stamp'] = Billrun_Util::generateArrayStamp($this->event);
		$this->event['record_type'] = $this->getDataRecordType($this->usaget, $this->event);
		if ($this->usaget === 'data') {
			$this->event['sgsn_address'] = $this->getSgsn($this->event);
		}

		if (isset($this->event['call_type'])) {
			$callTypesConf = Billrun_Factory::config()->getConfigValue('realtimeevent.callTypes', array());
			$this->usaget = (isset($callTypesConf[$this->event['call_type']]) ? $callTypesConf[$this->event['call_type']] : 'call');
		}

		$this->event['billrun_pretend'] = $this->isPretend($this->event);
		// we are on real time -> the time is now
		$this->event['urt'] = new MongoDate();

		Billrun_Factory::dispatcher()->trigger('realtimeAfterSetEventData', array(&$this->event, &$this->usaget));
	}

	protected function unifyLines($lines) {
		switch ($this->usaget) {
			case ('data'):
				return $this->unifyDataLines($lines);
			default:
				return current($lines);
		}
	}

	protected function unifyDataLines($lines) {
		$current = current($lines);
		$current["mscc_data"] = array();
		foreach ($lines as $line) {
			$line["mscc_data"][0]['granted_return_code'] = ( isset($line['granted_return_code']) ? $line['granted_return_code'] : null);
			$line["mscc_data"][0]['usagev'] = ( isset($line['usagev']) ? $line['usagev'] : 0);
			$current["mscc_data"][] = $line["mscc_data"][0];
		}
		$current['session_id'] = $this->event['session_id']; // returns the original session_id of the request to the unified response (and not the modified one)
		return $current;
	}

	protected function setEventDataEvents() {
		if ($this->usaget === 'data') {
			foreach ($this->event["mscc_data"] as $index => $mscc_service) {
				$new_event = $this->event;
				$new_event["mscc_data"] = array(0 => $mscc_service);
				$new_event['usaget'] = $this->usaget;
				$new_event['session_id'] .= $mscc_service['rating_group'];  // each one of the splitting events should have different session_id (for rebalance and unify purposes)
				$new_event['stamp'] = Billrun_Util::generateArrayStamp($new_event);

				$this->events[] = $new_event;
			}
		}

		if (!sizeof($this->events)) {
			$this->events[] = $this->event;
		}
	}

	protected function getSgsn($event) {
		$sgsn = 0;
		if (isset($event['service']['sgsn_address'])) {
			$sgsn = $event['service']['sgsn_address'];
		} else if (isset($event['sgsn_address'])) {
			$sgsn = $event['sgsn_address'];
		} else if (isset($event['sgsnaddress'])) {
			$sgsn = $event['sgsnaddress'];
		}
		return $sgsn;
	}

	protected function getDataRecordType($usaget, $data) {
		if (in_array($usaget, Billrun_Util::getCallTypes())) {
			return $data['api_name'];
		}

		switch ($usaget) {
			case('data'):
				$requestCode = $data['request_type'];
				$requestTypes = Billrun_Factory::config()->getConfigValue('realtimeevent.data.requestType', array());
				foreach ($requestTypes as $requestTypeDesc => $requestTypeCode) {
					if ($requestCode == $requestTypeCode) {
						return strtolower($requestTypeDesc);
					}
				}
				return false;
			case('sms'):
				return 'sms';
			case('mms'):
				return 'mms';
			case('service'):
				return 'service';
		}

		Billrun_Factory::log("No record type found. Params: " . print_R($usaget) . "," . print_R($data), Zend_Log::ERR);
		return false;
	}

	/**
	 * Gets the event type for rates calculator
	 * 
	 * @return string event type
	 * @todo Get values from config
	 */
	protected function getEventType() {
		if (in_array($this->usaget, Billrun_Util::getCallTypes())) {
			return 'callrt';
		}

		//TODO: move to config
		switch ($this->usaget) {
			case ('sms'):
				return 'smsrt';
			case ('mms'):
				return 'mmsrt';
			case ('data'):
				return 'gy';
			case ('service'):
				return 'service';
		}

		Billrun_Factory::log("No event type found. Usaget: " . print_R($this->usaget), Zend_Log::ERR);
		return false;
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

		foreach ($this->events as $event) {
			$processor->addDataRow($event);
		}
		$processor->process();
		$allLines = $processor->getAllLines();

		$unifiedAnswer = $this->unifyLines($allLines);
		return $unifiedAnswer;
	}

	/**
	 * Send respond
	 * 
	 * @param type $data
	 * @return boolean
	 */
	protected function respond($data) {
		$encoder = Billrun_Encoder_Manager::getEncoder(array(
				'usaget' => $this->usaget
		));
		if (!$encoder) {
			Billrun_Factory::log('Cannot get encoder', Zend_Log::ALERT);
			return false;
		}

		$responder = Billrun_ActionManagers_Realtime_Responder_Manager::getResponder($data);
		if (!$responder) {
			Billrun_Factory::log('Cannot get responder', Zend_Log::ALERT);
			return false;
		}

		$params = array('root' => 'response');
		$response = $encoder->encode($responder->getResponse(), $params);
		$this->getController()->setOutput(array($response, 1));
//		$this->getView()->outputMethod = 'print_r';

		return $response;
		// Sends response
		//$responseUrl = Billrun_Factory::config()->getConfigValue('IN.respose.url.realtimeevent');
		//return Billrun_Util::sendRequest($responseUrl, $response);
	}

	/**
	 * Checks if the row should really decrease balance from the subscriber's balance, or just pretend
	 * 
	 * @return boolean
	 */
	protected function isPretend($event) {
		return (in_array($this->usaget, Billrun_Util::getCallTypes()) && $event['record_type'] === 'start_call') ||
                        ($this->usaget === 'data' && $event['record_type'] === 'initial_request');
	}

}
