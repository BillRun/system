<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing abstract generator ilds class
 * require to generate xml for each account
 * require to generate csv contain how much to credit each account
 *
 * @package  Billing
 * @since    0.5
 */
class Billrun_Data_Test extends Billrun_Data {
	
	protected $testScript = array();
	protected $testId = false;
	
	public function __construct($testConfig) {
		$this->testScript = $testConfig->getRawData();
		$this->testId = $this->testScript['test_id'];
	}
	
	/**
	 * Load the  current test script
	 */
	static public function getCurrent($testId = false) {
		Billrun_Factory::log()->log("Loading latest  Test Configuration.");
		$query = array( 'key' => 'call_generator', 'from'=> array( '$lt' => new MongoDate(time())) );
		if($testId) {
			$query['test_id'] = $testId;
		}
		$testConfig = Billrun_Factory::db($query)->configCollection()->query()->cursor()->sort(array('urt' => -1))->limit(1)->current();
		
		return !$testConfig->isEmpty() ? new Billrun_Data_Test($testConfig) : null;
	}

	
	/**
	 * Check if the configuration has been updated.
	 */
	static public function isConfigUpdated($currentConfig) {
		//Billrun_Factory::log("Checking configuration update  relative to: ".date("Y-m-d H:i:s",  $currentConfig['urt']->sec));
		$currTime = new MongoDate(time());	
		$retVal = Billrun_Factory::db()->configCollection()->query(array('key' => 'call_generator','from'=> array('$lt'=> new MongoDate(time())),			
				'urt' => array(	'$gt' => $currentConfig['urt']  ,'$lte' =>  $currTime ) //@TODO add top limit to loaded configuration
			))->cursor()->limit(1)->current();
		return !$retVal->isEmpty();
	}
	
	/**
	 * Check if the current time is an active time (actually  making calls) for the script
	 * @return the time in second within the  active period  or false if not in active period.
	 */
	public function isInActivePeriod() {		
		$script = $this->testScript;
		if($this->shouldTestBeActive()) {
			usort($script, function($a,$b) { return intval($a['call_id']) - intval($b['call_id']);});
			$start = strtotime(reset($script)['time']);
			$end = strtotime(end($script)['time']) ;
			$endOffset = $end > $start  ? $end - $start : $end + 86400 - $start;
			$currentOffset = time() - $start;

			if ($endOffset > $currentOffset && $currentOffset > 0) {
				return $currentOffset;
			}
		}
		return false;
	}
	
	/**
	 * Check if a test is finished.
	 * @param type $script
	 * @return type
	 */
	public function isTestFinished() {
		return intval($this->testScript['call_count']) < $this->scriptFinshedCallsCount($this->testScript);
	}
	
	/**
	 * check if a given  test script should  be perfoemed 
	 * @param type $testScript the  test script to check
	 * @return boolean true  if the  test should run  false otherwise
	 */
	public function shouldTestBeActive() {
		return time() > $this->testScript['from']->sec && !$this->isTestFinished($this->testScript) &&
				in_array(date("w"),Billrun_Util::getFieldVal($this->testScript['active_days'],array(0,1,2,3,4,5,6)) );
	}
	/**
	 * 
	 * @return type
	 */
	public function isWorking() {
		return Billrun_Util::getFieldVal($this->testScript['state'], 'start') == 'start' && !$this->isTestFinished();
	}
	
	public function save($data = false) {
	
	}
	

	//========================================================================================
	
	protected function updateTest($param) {
		if($this->isConfigUpdated($this)) {
			$newData = $this->getCurrent($this->testId);
			$this->testScript = $newData->getTestData();
		}
	}
	
	/**
	 * count the  call that where done for a given script
	 * @param type $script
	 * @return type
	 */
	protected function scriptFinshedCallsCount($script) {
		return Billrun_Factory::db()->linesCollection()->query(array('type'=> 'generated_call','urt'=> array('$gt' => $script['from'], 'test_id'=> $script['test_id'])))->cursor()->count(true);
	}

	
	//---------------------------------- GETTERS / SETTERS ----------------------------------
	
	public function __get($name) {
		return Billrun_Util::getFieldVal($this->testScript[$name], null);
	}
	
	public function getTestData() {
		return $this->testScript;
	}

}
