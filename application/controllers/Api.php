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
	 * @param string $key
	 * @param mixed $value
	 */
	public function setOutput($key, $value) {
		$this->output->$key = $value;
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
		$this->getView()->outputMethod = Billrun_Factory::config()->getConfigValue('api.outputMethod');
	}

}