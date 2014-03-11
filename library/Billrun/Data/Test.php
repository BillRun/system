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
		parent::__construct($testConfig->getRarData());
		$this->testScript = $this->data['test_script'];
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
		$testConfig = Billrun_Factory::db($query)->configCollection()->query()->cursor()->sort(array('from' => -1,'urt' => -1))->limit(1)->current();
		
		return !$testConfig->isEmpty() ? new Billrun_Data_Test($testConfig) : null;
	}
	/**
	 *  Gety  all or some of the tests that are/were scheduled
	 * @param type $query 
	 * @return type
	 */
	static public function  get($query= array()) {
			$query = array_merge($query,array( 'key' => 'call_generator' ));
		
		$results = Billrun_Factory::db($query)->configCollection()->query()->cursor()->sort(array('urt' => -1));
		foreach ($results as  $testConfig) {
			$tests[] = new Billrun_Data_Test($testConfig);
		}
		return $tests;
	}

	
	/**
	 * Check if the configuration has been updated.
	 */
	static public function isConfigUpdated($currentConfig) {
		//Billrun_Factory::log("Checking configuration update  relative to: ".date("Y-m-d H:i:s",  $currentConfig['urt']->sec));
		$currTime = new MongoDate(time());	
		$retVal = Billrun_Factory::db()->configCollection()->query(array('key' => 'call_generator','from'=> array('$lt'=> new MongoDate(time())),			
				'urt' => array(	'$gt' => $currentConfig['urt']  ,'$lte' =>  $currTime ) 
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
	
	/**
	 * 
	 * @return boolean
	 */
	public function save() {
		Billrun_Factory::log()->log("Saving Config", Zend_Log::INFO);
		
		$data = $this->data;
		$data['test_script'] = $this->testScript;
		$data['test_id'] = $this->testId;
		$data['urt'] = new MongoDate( time() ); 
		$data['from'] = new MongoDate($data['from']);
		$data['to'] = new MongoDate($data['to']);
		$configCol = Billrun_Factory::db()->configCollection();		
		$entity = new Mongodloid_Entity($data,$configCol);		
		
		if ($entity->isEmpty() || $entity->save($configCol) === false) {
			Billrun_Factory::log()->log('Failed to store configuration into DB',Zend_Log::ALERT);
			return false;
		}
		
		$this->removeOldEnteries($entity['key'],$data['from'],$data['to'],$data['urt']);
		
		Billrun_Factory::log()->log("Saved Config", Zend_Log::INFO);
		return true;
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

	
	/**
	 * Remove old config entries
	 * @param type $keythe  key to remove old entries for.
	 */
	protected function removeOldEnteries($key,$from , $to, $urt) {
		$oldEntries = Billrun_Factory::db()->configCollection()->query(array('key' => $key))->cursor()->sort(array('urt'=>-1))->skip(static::CONCURRENT_CONFIG_ENTRIES);
		foreach ($oldEntries as $entry) {
			$entry->collection(Billrun_Factory::db()->configCollection());
			$entry->remove();
		}
		
		return Billrun_Factory::db()->configCollection()->remove(array('key' => $key, 'from' => array('$gte' => $from, '$lte' => $to),'urt' => array('$lt'=> $urt)));
		
	}
	
	//---------------------------------- GETTERS / SETTERS ----------------------------------
	
	public function __get($name) {
		return Billrun_Util::getFieldVal($this->testScript[$name], null);
	}
	
	public function getTestData() {
		return $this->testScript;
	}

}
