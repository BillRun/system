<?php
/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing handler class that can take of process 
 * The handler can be extend the system to plugins
 *
 * @package  handler
 * @since    1.0
 */
class Billrun_Handler extends Billrun_Base {

	static public function getInstance() {
		
		$args = func_get_args();

		return new self($args);
	}

	/**
	 * method to execute simple handler that collect data to handle
	 */
	public function execute() {
		
		$this->log->log("Handler execute start", Zend_Log::INFO);
		
		$collect_data = $this->collect();

		$this->alert($collect_data);

		$this->markdown($collect_data);
		
		$this->log->log("Handler execute finished", Zend_Log::INFO);
		
	}

	/**
	 * method to collect the data to handle
	 * 
	 * @return array list of handling items
	 */
	protected function collect() {

		$this->log->log("Handler collect start", Zend_Log::INFO);

		$items = $this->dispatcher->trigger('handlerCollect');
		
		$this->log->log("Handler collect finished", Zend_Log::INFO);

		return $items;
	}

	/**
	 * method to alert the data collected
	 * 
	 * @param array $items list of items to be alerted
	 * 
	 * @return boolean true if success
	 */
	protected function alert(&$items) {
		$this->log->log("Handler alert start", Zend_Log::INFO);
		
		if (!is_array($items) || !count($items)) {
			$this->log->log("Handler alert items not found", Zend_Log::NOTICE);
			return FALSE;
		}
		
		foreach ($items as $plugin => &$plguinItems) {
			foreach ($plguinItems as  &$item) {	
				$this->dispatcher->trigger('handlerAlert', array(&$item,$plugin));
			}
		}
		// TODO: check return values
		
		$this->log->log("Handler alert finished", Zend_Log::INFO);
		return TRUE;
	}

	/**
	 * method to mark all the lines that take care in the handler to avoid double runnning
	 * 
	 * @param array $data list of items to be mark down
	 * 
	 * @return boolean true if success
	 */
	protected function markdown(&$items) {
		$this->log->log("Handler markdown start", Zend_Log::INFO);

		if (!is_array($items) || !count($items)) {
			$this->log->log("Handler markdown items not found", Zend_Log::NOTICE);
			return FALSE;
		}

		foreach ($items as $plugin => &$plguinItems) {
			foreach ($plguinItems as  &$item) {	
				$this->dispatcher->trigger('handlerMarkDown', array(&$item,$plugin));
			}
		}
		// TODO: check return values
		
		$this->log->log("Handler markdown finished", Zend_Log::INFO);
		return TRUE;
	}

}
