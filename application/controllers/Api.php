<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing api controller class
 *
 * @package  Controller
 * @since    0.5
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
		Yaf_Loader::getInstance(APPLICATION_PATH . '/application/helpers')->registerLocalNamespace("Action");
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
		try {
			// DB heartbeat
			Billrun_Factory::db()->linesCollection()
				->query()->cursor()->limit(1)->current(); 
			Billrun_Factory::db()->balancesCollection()
				->query()->cursor()->limit(1)->current(); 
			$msg = 'SUCCESS';
			$status = 1;
		} catch (Exception $ex) {
			Billrun_Factory::log('API Heartbeat failed. Error ' . $ex->getCode() . ": " . $ex->getMessage(), Zend_Log::EMERG);
			$msg = 'FAILED';
			$status = 0;
		}
		
		if ($this->getRequest()->get('simple')) {
			$this->setOutput(array($msg, 1));
			$this->getView()->outputMethod = 'print_r';
		} else {
			$this->setOutput(array(array('status' => $status, 'message' => 'Billrun API ' . ucfirst($msg))));
		}

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
		$num_args = count($args);
		if (is_array($args[0])) {
			foreach ($args[0] as $key => $value) {
				$this->setOutput($key, $value);
			}
			return true;
		} else if ($num_args == 1) {
			$this->output = $args[0];
			return true; //TODO: shouldn't it also return true?
		} else if ($num_args == 2) {
			$key = $args[0];
			$value = $args[1];
			$this->output->$key = $value;
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
	 * @param callable $output_method The callable to be called.
	 */
	protected function setOutputMethod() {
		$action = $this->getRequest()->getActionName();
		$usaget = $this->getRequest()->get('usaget');
		$output_methods = Billrun_Factory::config()->getConfigValue('api.outputMethod');
		if (!empty($action) && !empty($usaget) &&
			isset($output_methods[$action][$usaget]) && !is_null($output_methods[$action][$usaget])) {
			$this->getView()->outputMethod = $output_methods[$action][$usaget];
			return;
		}
		if (isset($output_methods[$action]) && is_string($output_methods[$action])) {
			$this->getView()->outputMethod = $output_methods[$action];
			return;
		}
		Billrun_Factory::log('No output method defined; set to json encode', Zend_Log::DEBUG);
		$this->getView()->outputMethod = array('Zend_Json', 'encode');
	}

}
