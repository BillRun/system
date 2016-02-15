<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing api controller class
 *
 * @package  Controller
 * @since    1.0
 */
class ApiController extends Yaf_Controller_Abstract {

	/**
	 * api call output. the output will be converted to json on view
	 * 
	 * @var mixed
	 */
	protected $output;

	/**
	 * initialize method for yaf controller (instead of constructor)
	 */
	public function init() {
		// all output will be store at class output class
		$this->output = new stdClass();
		$this->getView()->output = $this->output;
		// set the actions autoloader
		$loader = Yaf_Loader::getInstance(APPLICATION_PATH . '/application/helpers');
		$loader->registerLocalNamespace("Action");
		$this->setActions();
		$this->setOutputMethod();
	}

	/**
	 * method to set the available actions of the api from config declaration
	 */
	protected function setActions() {
		$this->actions = Billrun_Factory::config()->getConfigValue('api.actions', array());
	}
	
	/**
	 * default method of api. Just print api works
	 */
	public function indexAction() {
		$this->setOutput('status', true);
		$this->setOutput('message', "Billrun API works");
	}

	/**
	 * method to set view output
	 * 
	 * @param mixed $key array of key=>value or key (later need to be with $value)
	 * @param mixed $value optional in case key is string this the value of it
	 * 
	 * @return boolean on success, else false
	 */
	public function setOutput() {
		$args = func_get_args();
		if (count($args) == 2) {
			$key = $args[0];
			$value = $args[1];
			$this->output->$key = $value;
			return true;
		} else if (is_array($args[0])) {
			foreach($args[0] as $key => $value) {
				$this->setOutput($key, $value);
			}
			return true;
		}
		return false;
	}

	/**
	 * method to unset value from the view output
	 * 
	 * @param type $key
	 * 
	 * @return mixed the value of the key that unset
	 */
	public function unsetOutput($key) {
		$value = $this->output->$key;
		unset($this->output->$key);
		return $value;
	}
	
	/**
	 * method to set how the api output method
	 */
	protected function setOutputMethod() {
		$action = $this->getRequest()->getActionName();
		$output_methods = Billrun_Factory::config()->getConfigValue('api.outputMethod');
		if (!isset($output_methods[$action]) || is_null($output_methods[$action])) {
			Billrun_Factory::log()->log('No output method defined; set to json encode', Zend_Log::DEBUG);
			$this->getView()->outputMethod = array('Zend_Json', 'encode');
		} else {
			$this->getView()->outputMethod = $output_methods[$action];
		}
	}

}