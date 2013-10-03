<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing call generator class
 * Make and  receive call  base on several  parameters
 *
 * @package  Billing
 * @since    0.5
 */
class Billrun_Generator_Calls extends Billrun_Generator {

	const TYPE_REGULAR = 'regular';
	const TYPE_BUSY = 'busy';
	const TYPE_VOICE_MAIL = 'voice_mail';
	const TYPE_NO_ANSWER = 'no_answer';
	const MIN_MILLI_RESOLUTION = 1000;
	const BUSY_WAIT_TIME = 5;

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'calls';

	/**
	 * The script to generate calls by.
	 */
	protected $testScript = array();

	/**
	 * The test identification.
	 */
	protected $testId = false;

	/**
	 * The  state of the call generator.
	 */
	protected $isWorking = true;	
	
	/**
	 * The calling device.
	 * @var Gsmodem_Gsmodem
	 */
	protected $modemDevices = array();

	public function __construct($options) {
		parent::__construct($options);
		if (isset($options['path_to_calling_devices'])) {
			foreach ($options['path_to_calling_devices'] as $value) {
				$modem = new Gsmodem_Gsmodem($value['device']);
				if ($modem->isValid()) {
					$modem->registerToNet();
					if (isset($value['number'])) {
						$modem->setNumber($value['number']);
					}
					$this->modemDevices[] = $modem;
				}
			}
		}
	}

	/**
	 * Generate the calls as defined in the configuration.
	 */
	public function generate() {
		if(!isset($this->testScript['test_script'])) {
			Billrun_Factory::log("No test script configured!", Zend_Log::NOTICE);
			return false;
		}
		if (count($this->modemDevices) > 0) {
			while ($this->isWorking) {
				if ($this->isConfigUpdated($this->testScript)) {
					$this->load();
				}
				$this->actOnScript($this->testScript['test_script']);
			}
		} else {
			Billrun_Factory::log("No active modem devices.", Zend_Log::NOTICE);
		}
		return true;
	}

	/**
	 * Load the script
	 */
	public function load() {

		$testConfig = Billrun_Factory::db()->configCollection()->query(array('key' => 'call_generator'))->cursor()->sort(array('unified_record_time' => -1))->limit(1)->current();
		if (!$testConfig->isEmpty()) {
			$this->testScript = $testConfig->getRawData();
			$this->testId = $this->testScript['test_id'];
			//@TODO FOR DEBUG REMOVE !
			$offset = 1;
			foreach ($this->testScript['test_script'] as &$value) {
				$value['time'] = date("H:i:s", strtotime("+{$offset} minutes"));
				$offset+=1;
			}
			//@TODO FOR DEBUG END
		}
		//Billrun_Factory::log("got script : " . print_r($this->testScript, 1));
	}

	/**
	 * Act on a given script
	 * @param array $script the script to act on.
	 */
	protected function actOnScript($script) {
		$action = $this->waitForNextAction($script);
		if ($action) {
			//check if the number  speciifed in the action is one of the connected modems if so  act on the action.
			foreach( array('from' => true, 'to' => false) as $key => $isCalling ) {
				if($this->isConnectedModemNumber($action[$key]) != false) {
					if (!pcntl_fork()) {
						$this->scriptAction($action,$isCalling);
						exit(0);
					}
				}
			}
		}
	}

	/**
	 * Wait  until the next action time has reached.
	 * @param array $script the scrip actions list.
	 */
	protected function waitForNextAction($script) {
		$action = FALSE;
		foreach ($script as $scriptAction) {
			if ($scriptAction['time'] > date("H:i:s") &&
				$this->isConnectedModemNumber(array($scriptAction['from'], $scriptAction['to']))) {
				$action = $scriptAction;
				break;
			}
		}
		if ($action) {
			Billrun_Factory::log("Got action of type : {$action['action_type']} the should be run at : {$action['time']}, Waiting... ");
			while ($action['time'] >= date("H:i:s")) {
				usleep(static::MIN_MILLI_RESOLUTION / 4);
			};
			Billrun_Factory::log("Done Waiting.");
		}
		return $action;
	}

	/**
	 * Do a script action.
	 * @param array $action the action to do.
	 * @param boolean $isCalling is the action  is for the calling side.
	 */
	protected function scriptAction($action, $isCalling) {
		Billrun_Factory::log("Acting on action of type : {$action['action_type']}");
		$device = $this->getConnectedModemByNumber($action[$isCalling ? 'from' : 'to']);
		//make the calls and remember their results
		$call = $this->getEmptyCall();

		if ($isCalling) {
			if ($action['action_type'] == static::TYPE_BUSY) {
				sleep( Billrun_Factory::config('calls.busy_wait_time', static::BUSY_WAIT_TIME,'int') );
			}
			$this->makeACall($device, $call, $action['to']);
		} else {
			if ($action['action_type'] != static::TYPE_BUSY) {
				$this->waitForCall($device, $call, $action['action_type'], $action['duration'] + static::BUSY_WAIT_TIME );
			} else {
				$this->callToBusyNumber($device, $action['duration']);
				$call['calling_result'] = 'busy';
			}
		}

		if ($call['calling_result'] == Gsmodem_Gsmodem::CONNECTED) {
			$this->HandleCall($device, $call, $action['duration'], (($action['hangup'] == 'caller') == $isCalling) );
		}
		//$call['execution_end_time'] = date("YmdTHis");
		$this->save($action, $call, $isCalling);
	}

	/**
	 * Make a call as defiend  by the configuration.
	 * @param type $callRecord the  call record to save to the DB.
	 * @return mixed the call record with the data regarding making the call.
	 */
	protected function makeACall($device, &$callRecord, $numberToCall) {
		Billrun_Factory::log("Making a call to  {$numberToCall}");
		$callRecord['execution_start_time'] = new MongoDate(time());
		$callRecord['called_number'] = $numberToCall;
		$callRecord['calling_result'] = $device->call($numberToCall);

		return $callRecord['calling_result'];
	}

	/**
	 * Wait for a call.
	 * @param mixed $callRecord  the call record to save to the DB.
	 * @return mixed the call record with the data regarding the incoming call.
	 */
	protected function waitForCall($device, &$callRecord, $callType, $duration) {
		Billrun_Factory::log("Waiting for a call of type {$callType}");
		if ($device->waitForCall($duration) !== FALSE) {
			switch ($callType) {
				default:
				case 'regular':
						$callRecord['calling_result'] = $device->answer();
					break;
				case 'no_answer':
				case 'voice_mail':					
						$device->waitForRingToEnd();
						$callRecord['calling_result'] = 'ignored';
					break;
				case 'hangup' :
						$callRecord['calling_result'] = $device->hangUp();
					break;
			}
		} 

		return $callRecord['calling_result'];
	}

	/**
	 * Call to a assigned  number to keep the line busy.
	 */
	protected function callToBusyNumber($device, $duration) {
		Billrun_Factory::log("Calling to busy number : " . $this->getConfig('busy_number'));
		$call = array();
		$ret = $this->makeACall($device, $call, $this->getConfig('busy_number'));

		if ($ret == Gsmodem_Gsmodem::CONNECTED) {
			$this->HandleCall($device, $call, $duration);
		}
	}

	/**
	 * Handle an active call.
	 * @param type $callRecord the call record to save to the DB.
	 */
	protected function HandleCall($device, &$callRecord, $waitTime, $hangup  = true) {
		Billrun_Factory::log("Handling an active call.");
		$callRecord['call_start_time'] = new MongoDate(time());
		$callRecord['end_result'] = $device->waitForCallToEnd($hangup ? $waitTime : $waitTime + static::BUSY_WAIT_TIME*2);
		if ($callRecord['end_result'] == Gsmodem_Gsmodem::NO_RESPONSE ) {
				$device->hangUp();
				$callRecord['end_result'] = 'hang_up';
		} 

		$callRecord['call_end_time'] = new MongoDate(time());
		$callRecord['duration'] = $callRecord['call_end_time']->sec - $callRecord['call_start_time']->sec;
	}

	/**
	 * Get an empty call record to be save to the DB.
	 * @return Array representing the call record with initailized values. 
	 */
	protected function getEmptyCall() {
		return array(
			'execution_start_time' => new MongoDate(time()),
			'calling_result' => 'no_call',
			'call_start_time' => null,
			'end_result' => 'no_call',
			'call_end_time' => null,
			'duration' => 0,
			'execution_end_time' => null,
		);
	}

	/**
	 * Save q call made/received to  the DB.
	 * @param Array $calls containing the call recrods of the calls  that where made/received
	 * @return boolean
	 */
	protected function save($action, $call, $isCalling) {
		$call['execution_end_time'] = new MongoDate(time());
		$direction = $isCalling ? 'caller' : 'callee';
		$commonRec = array_merge($action, array('test_id' => $this->testId, 'date' => date('Ymd'), 'source' => 'generator', 'type' => 'generated_call'));
		$commonRec['stamp'] = md5(serialize($commonRec));
		$callData = array();
		foreach ($call as $key => $value) {
			$callData["{$direction}_{$key}"] = $value;
		}
		if ($isCalling) {
			$callData['unified_record_time'] = $call['call_start_time'] ? $call['call_start_time'] : $call['execution_start_time'];
		}

		return $this->safeSave(array('type' => 'generated_call', 'stamp' => $commonRec['stamp']), $callData, array_merge($callData, $commonRec));
	}

	/**
	 * Get config value for a given call instance and allow  for configuration overide.
	 * @param string $name the config  key  to look for
	 * @param array $callConfig the configuration override.
	 * @return mixed the config value in the override  or if it not found the config value in the general configuration 
	 * 			or FALSE if no value  was found to the config key.
	 */
	protected function getConfig($configKey, $callInstanceConfig = array()) {
		return Billrun_Factory::config()->getLocalInstanceConfig($this->getType() . '.' . $configKey, $callInstanceConfig, FALSE, 'string');
	}

	/**
	 * Check if the configuration has been updated.
	 */
	protected function isConfigUpdated($currentConfig) {
		$retVal = Billrun_Factory::db()->configCollection()->query(array('key' => 'call_generator',
				'unified_record_time' => array('$gt' => $currentConfig['unified_record_time']))
			)->cursor()->limit(1)->current();
		return !$retVal->isEmpty();
	}

	/**
	 * Check is a given number is one of the connected modems.
	 * @param type $numbers the  numbers  to check.
	 * @return boolean true is the on of the numbers  belongs to one of the connected modems false otherwise.
	 */
	protected function isConnectedModemNumber($numbers) {
		$numbers = is_array($numbers) ? $numbers : array($numbers);
		foreach ($numbers as $number) {
			if ($this->getConnectedModemByNumber($number) != FALSE) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if a number is one of the connected modem numbers.
	 * @param type $number the number to check.
	 * @return boolean|mixed the modem instace that has the number or false if none of the modem matches. 
	 */
	protected function getConnectedModemByNumber($number) {
		foreach ($this->modemDevices as $modem) {
			//Billrun_Factory::log('quering for modem number');
			if ($modem->getModemNumber() == $number) {
				return $modem;
			}
		}
		return false;
	}

	/**
	 * Save  with  findAndModifiy  to safely handle  concurrent db access.
	 * @param type $query the query to find the item to save.
	 * @param type $updateData the  data  to update the item with if  it exists in the DB
	 * @param type $newData the  data to create the  item in the  db  with
	 * @return boolean true  if the  save was successful  false otherwise.
	 */
	protected function safeSave($query, $updateData, $newData) {
		$linesCollec = Billrun_Factory::db()->linesCollection();
		$varifiyField = array_keys($updateData)[0];
		if (!($ret = $linesCollec->findAndModify(	$query, 
													array('$setOnInsert' => $newData), 
													array(), 
													array('upsert' => true, 'new' => true)) )|| 
				!isset($ret->getRawData()[$varifiyField]) || $ret->getRawData()[$varifiyField] != $updateData[$varifiyField] ) {

			if (!($ret = $linesCollec->findAndModify(	$query, 
														array('$set' => $updateData), 
														array(), 
														array('upsert' => false, 'new' => true)) ) || $ret->isEmpty()) {

				Billrun_Factory::log('Failed when trying to save : ' . print_r($updateData, 1));
				return false;
				
			}
		}

		Billrun_Factory::log('Successfully saved : ' . print_r($ret->getRawData(), 1));
		return true;
	}

}
