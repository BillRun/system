<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 * @deprecated since version 5.3
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
	use Billrun_Traits_Api_UserPermissions;

	protected $event = null;
	protected $usaget = null;

	/**
	 * method to execute realtime event
	 */
	public function execute() {
		$this->allowed();
		Billrun_Factory::log("Execute realtime event", Zend_Log::INFO);
		$this->event = $this->getRequestData();
		$this->setEventData();
		if (isset($this->event['reverse_charge']) && $this->event['reverse_charge']) {
			return $this->forward("reversecharge", array("event" => $this->event, "usaget" => $this->usaget));
		}
		if (isset($this->event['api_name']) && $this->event['api_name'] === 'healthcheck_in') {
			return $this->forward('healthcheck', array("event" => $this->event, "usaget" => $this->usaget));
		}
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

		if (!empty($request['request'])) {
			$requestBody = $request['request'];
		} else {
			$requestBody = file_get_contents("PHP://input");
		}

		return Billrun_Util::parseDataToBillrunConvention($decoder->decode($requestBody));
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
		if (isset($this->event['time_date'])) {
			$this->event['urt'] = new MongoDate(strtotime($this->event['time_date']));
		} else {
			// we are on real time -> the time is now
			$this->event['urt'] = new MongoDate();
		}

		Billrun_Factory::dispatcher()->trigger('realtimeAfterSetEventData', array(&$this->event, &$this->usaget));

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
		$processor->addDataRow($this->event);
		$processor->process();
		return current($processor->getAllLines());
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
		return (in_array($this->usaget, Billrun_Util::getCallTypes()) && $event['record_type'] === 'start_call');
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_WRITE;
	}

}
