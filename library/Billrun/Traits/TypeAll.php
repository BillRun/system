<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This Trait is used to allow controllers to proccess "type":"all" input
 *
 */
trait Billrun_Traits_TypeAll {
	
	/**
	 * An array that holds all the file types for this instance.
	 */
	protected $allTypes;
	
	/**
	 * Handle input type "all"
	 * @param array $options Array of input options.
	 */
	protected function handleTypeAll($options) {
		// Validate the input options.
		if(!isset($options['type']) || (strtolower($options['type']) != "all")) {
			// Nothing to do.
			return false;
		}
		
		$this->allTypes = $this->getAllTypes();
		
		$cmd = $this->getCMD();
		
		// TODO: UNCOMMENT THIS TO USE FORK INSTEAD OF SYSTEM CALL		
//		$tempOptions = $options;
//		$handleFunction = $this->getHandleFunction();

		foreach ($this->allTypes as $type => $timeout) {
			if(!$this->isTimeoutPassed($type, $timeout)) {
				Billrun_Factory::log("Skipping " . $type . " " . $this->getNameType());
				continue;
			}
				
			$tempCmd = $cmd . " " . $type;
			Billrun_Factory::log('TypeAll invokes command: ' . $tempCmd, Zend_Log::DEBUG);
			Billrun_Util::forkProcessCli($tempCmd);
			
			// TODO: UNCOMMENT THIS TO USE FORK INSTEAD OF SYSTEM CALL
//			$tempOptions['type'] = $type;
//			pcntl_fork();
//			$this->$handleFunction($tempOptions);
//			exit();
		}
		
		return true;
	}
	
	/**
	 * Check if the timeout for this type had passed.
	 * @param type $type
	 * @param type $timeout
	 * @return boolean
	 */
	protected function isTimeoutPassed($type, $timeout) {
		$cache = Billrun_Factory::cache();
		// If we fail to get cache, it means that we are on a dev environment, 
		// trigger the process anyway.
		if (empty($cache)) {
			Billrun_Factory::log("No cache available. Ignoring timeout: Executing type " . $type, Zend_Log::WARN);
			return true;
		}
		$key = $type . "_timeout";
		$prefix = $this->getNameType();

		// Check if the buffer timeout has passed
		$lastRun = $cache->get($key, $prefix);
		if($lastRun) {
			$timePassed = time() - $lastRun;
			$bufferTimeout = strtotime($timeout);
			if($timePassed < $bufferTimeout) {
				return false;
			}
		}

		// Update last run
		$cache->set($key, time(), $prefix);
			
		return true;
	}
	
	/**
	 * Get all the file types for this instance.
	 * @return array of file types.
	 */
	protected function getAllTypes() {
		$rawArray = Billrun_Factory::config()->file_types->toArray();
		$typeArray = array();
		$name = $this->getNameType();
		foreach ($rawArray as $current) {
			if(!isset($current[$name]) || !Billrun_Config::isFileTypeConfigEnabled($current)) {
				continue;
			}
			$typeArray[$current['file_type']] = $current[$name]['buffer_timeout'] ?? '';
		}
		return $typeArray;
	}
	
	protected abstract function getNameType();
	
	protected abstract function getCMD();

	/**
	 * Get the handle function
	 */
	protected abstract function getHandleFunction();
}
