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
		$this->setInputDecode();
		$this->setOutputEncode();
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
		$this->setOutput(array(array('status' => true, 'message' => 'Billrun API works')));
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
			$this->outputEncode();
			return true;
		} else if ($num_args == 1) {
			$this->output = $args[0];
			//return true; //TODO: shouldn't it also return true?
		} else if ($num_args == 2) {
			$key = $args[0];
			$value = $args[1];
			$this->output->$key = $value;
			return true;
		}
		return false;
	}

	protected function outputEncode() {
		switch ($this->getView()->outputEncode) {
			case ('array'):
				$this->getView()->output = (array) $this->output;
				break;
			case('json'):
				$this->getView()->output = array(json_encode((array) $this->output));
				break;
			case ('xml'):
				$this->getView()->output = array($this->arrayToXML((array) $this->output, "response"));
				break;
		}
	}

	/**
	 * Assistance function to convert array to xml
	 * 
	 * @param array $array
	 * @param string $root name of the root node in the xml
	 * @return string xml
	 */
	protected function arrayToXML($array, $root = 'root') {
		return "<?xml version='1.0'?>" . "<" . $root . ">" . $this->getXMLBody($array) . "</" . $root . ">";
	}

	/**
	 * Assistance function to get inner nodes of the xml body
	 * 
	 * @param type $value
	 * @return string
	 */
	protected function getXMLBody($value) {
		$ret = '';
		if (is_array($value)) {
			foreach ($value as $key => $val) {
				$ret .= '<' . $key . '>' . $this->getXMLBody($val) . '</' . $key . '>';
			}
		} else {
			return $value;
		}
		return $ret;
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
		$output_methods = Billrun_Factory::config()->getConfigValue('api.outputMethod');
		if (!isset($output_methods[$action]) || is_null($output_methods[$action])) {
			Billrun_Factory::log('No output method defined; set to json encode', Zend_Log::DEBUG);
			$this->getView()->outputMethod = array('Zend_Json', 'encode');
		} else {
			$this->getView()->outputMethod = $output_methods[$action];
		}
	}

	/**
	 * Set the api input decode: json/xml/...
	 */
	protected function setInputDecode() {
		$action = $this->getRequest()->getActionName();
		$input_decodes = Billrun_Factory::config()->getConfigValue('api.inputDecode');
		if (!isset($input_decodes[$action]) || is_null($input_decodes[$action])) {
			Billrun_Factory::log('No input encode defined; set to json', Zend_Log::DEBUG);
			$this->getView()->inputDecode = 'json';
		} else {
			$this->getView()->inputDecode = $input_decodes[$action];
		}
	}

	/**
	 * Set the api output encode: json/xml/...
	 */
	protected function setOutputEncode() {
		$action = $this->getRequest()->getActionName();
		$output_encodes = Billrun_Factory::config()->getConfigValue('api.outputEncode');
		if (!isset($output_encodes[$action]) || is_null($output_encodes[$action])) {
			Billrun_Factory::log('No output decode defined; set to array', Zend_Log::DEBUG);
			$this->getView()->outputEncode = 'array';
		} else {
			$this->getView()->outputEncode = $output_encodes[$action];
		}
	}

	/**
	 * Converts string input to array, according to the input decode
	 * 
	 * @param string $inputStr The input as string
	 * @return array Converted input (as array)
	 */
	public function getInput($inputStr) {
		switch ($this->getView()->inputDecode) {
			case('json'):
				return json_decode($inputStr, JSON_OBJECT_AS_ARRAY);
			case ('xml'):
				$xmlArr = (array) simplexml_load_string($inputStr);
				return json_decode( json_encode($xmlArr) , 1);
		}

		return $inputStr;
	}

}
