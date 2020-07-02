<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Paging
 *
 * @author eran
 */
class Billrun_Cycle_Paging {
	
	protected $defaultOptions = array(
			'sleepTime'=> 100,
			'size'=> 100,
			'identifingQuery' => array('billrun_key' => '201705'),
			'maxProcesses' => 20,
		);
	protected $options = array();
	protected $pagerCollection = null;
	protected $invoicing_day = null;
	
	public function __construct($options, $pagingCollection) {
		$this->options = Billrun_Util::getFieldVal($options, $this->defaultOptions);
		$this->pagerCollection = $pagingCollection;
		$this->host = Billrun_Util::getHostName();
		if (Billrun_Factory::config()->isMultiDayCycle()) {
			if (empty($options['invoicing_day'])) {
				Billrun_Factory::log()->log("No invoicing day was found in Cycle Paging, the default one was taken.", Zend_Log::WARN);
				$this->invoicing_day = Billrun_Factory::config()->getConfigChargingDay();
			} else {
				$this->invoicing_day = $options['invoicing_day'];
			}			
		}
	}
	
	/**
	 * Finding which page is next in the biiling cycle
	 * @param the number of max tries to get the next page in the billing cycle
	 * @return number of the next page that should be taken
	 */
	public function getPage($zeroPages, $retries = 100) {
		if ($retries <= 0) { // 100 is arbitrary number and should be enough
			Billrun_Factory::log()->log("Failed getting next page, retries exhausted", Zend_Log::ALERT);
			return false;
		}
		if(!$this->validateMaxProcesses()) {
			return false;
		}
		
		$nextPage = $this->getNextPage();
		if($nextPage === false) {
			Billrun_Factory::log("getPage: Failed getting next page.");
			return false;
		}
		
		if($this->checkExists($nextPage)) { // we couldn't lock the next page (other process did it)
			$error = "Page number ". $nextPage ." already exists.";
			Billrun_Factory::log($error . " Trying Again...", Zend_Log::NOTICE);
			usleep($this->sleepTime);
			return $this->getPage($zeroPages, $retries - 1);
		}
		
		return $nextPage;
	}
	
	/**
	 * Validate the max processes config valus
	 * @param string $host - Host name value
	 * @param int $maxProcesses - The max number of proccesses
	 * @return boolean true if valid
	 */
	protected function validateMaxProcesses() {
		$query = array_merge( $this->identifingQuery, array('page_size' => $this->size, 'host'=> $this->host,'end_time' => array('$exists' => false)) );
		$processCount = $this->pagerCollection->query($query)->count();
		if ($processCount >= $this->maxProcesses) {
			Billrun_Factory::log("Host ". $host. "is already running max number of [". $this->maxProcesses . "] processes", Zend_Log::DEBUG);
			return false;
		}
		return true;
	}
	
	/**
	 * Get the next page index
	 * @return boolean|int
	 */
	protected function getNextPage() {
		$query = array_merge($this->identifingQuery,array('page_size' => $this->size));
		$currentDocument = $this->pagerCollection->query($query)->cursor()->sort(array('page_number' => -1))->limit(1)->current();
		if (is_null($currentDocument)) {
			Billrun_Factory::log("getNexPage: failed to retrieve document");
			return false;
		}
		
		// First page
		if (!isset($currentDocument['page_number'])) {
			return 0;
		} 
		
		return $currentDocument['page_number'] + 1;
	}
	
	/**
	 * Check if 
	 * @param type $nextPage
	 * @param type $host
	 * @return type
	 */
	protected function checkExists($nextPage) {
		$query = array_merge($this->identifingQuery, array('page_number' => $nextPage, 'page_size' => $this->size));
		if (!empty($this->invoicing_day)) {
			$query['invoicing_day'] = $this->invoicing_day;
		}
		$modifyQuery = array_merge($query, array('host' => $this->host, 'start_time' => new MongoDate()));
		$modify = array('$setOnInsert' => $modifyQuery);
		$checkExists = $this->pagerCollection->findAndModify($query, $modify, null, array("upsert" => true));
		
		return !$checkExists->isEmpty();
	}
	
	//-------------------------------------------------
	
	public function __get($name) {
		return isset($this->options[$name]) ? $this->options[$name] : $this->defaultOptions[$name];
	}
}
