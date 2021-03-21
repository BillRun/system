<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing handler class that can take of process 
 * The handler can be extend the system to plugins
 *
 * @package  handler
 * @since    0.5
 */
class Billrun_Handler extends Billrun_Base {

	/**
	 * Options passed from  the owner of the handler object
	 */
	protected $options = array();

	public function __construct($options = array()) {
		$this->options = $options;
	}

	/**
	 * method to retrieve instance of Billrun Handler
	 * 
	 * @return self instance
	 * @todo make args signature to avoid over loading of instance
	 */
	public static function getInstance($args = array()) {

		//$args = func_get_args();
		return new self($args);
	}

	/**
	 * method to execute simple handler that collect data to handle
	 */
	public function execute() {

		Billrun_Factory::log("Handler execute start", Zend_Log::INFO);

		$collect_data = $this->collect();

		$this->alert($collect_data);

		$this->markdown($collect_data);

		$this->notify();

		Billrun_Factory::log("Handler execute finished", Zend_Log::INFO);
	}

	/**
	 * method to collect the data to handle
	 * 
	 * @return array list of handling items
	 */
	protected function collect() {

		Billrun_Factory::log("Handler collect start", Zend_Log::INFO);

		$items = Billrun_Factory::dispatcher()->trigger('handlerCollect', array($this->options));

		Billrun_Factory::log("Handler collect finished", Zend_Log::INFO);

		return $items;
	}

	/**
	 * method to notify other systems  that an event has happend.
	 * 
	 * @return array list of notifyed items
	 */
	protected function notify() {

		Billrun_Factory::log("Handler notify start", Zend_Log::INFO);

		$items = Billrun_Factory::dispatcher()->trigger('handlerNotify', array($this, $this->options));

		Billrun_Factory::log("Handler notify finished", Zend_Log::INFO);

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
		Billrun_Factory::log("Handler alert start", Zend_Log::INFO);

		if (!is_array($items) || !count($items)) {
			Billrun_Factory::log("Handler alert items not found", Zend_Log::NOTICE);
			return FALSE;
		}

		Billrun_Factory::dispatcher()->trigger('beforeHandlerAlert', array(&$items));

		foreach ($items as $plugin => &$pluginItems) {
			// ggsn
			Billrun_Factory::dispatcher()->trigger('handlerAlert', array(&$pluginItems, $plugin, $this->options));
		}

		Billrun_Factory::dispatcher()->trigger('afterHandlerAlert', array(&$items));

		// TODO: check return values

		Billrun_Factory::log("Handler alert finished", Zend_Log::INFO);
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
		Billrun_Factory::log("Handler markdown start", Zend_Log::INFO);

		if (!is_array($items) || !count($items)) {
			Billrun_Factory::log("Handler markdown items not found", Zend_Log::NOTICE);
			return FALSE;
		}

		Billrun_Factory::dispatcher()->trigger('beforeHandlerMarkDown', array(&$items));

		foreach ($items as $plugin => &$pluginItems) {
			Billrun_Factory::dispatcher()->trigger('handlerMarkDown', array(&$pluginItems, $plugin, $this->options));
		}

		Billrun_Factory::dispatcher()->trigger('afterHandlerMarkDown', array(&$items));

		// TODO: check return values

		Billrun_Factory::log("Handler markdown finished", Zend_Log::INFO);
		return TRUE;
	}

}
