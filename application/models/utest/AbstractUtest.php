<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Basic unit test model class
 *
 * @package  Models
 * @subpackage uTest
 * @since    4.0
 */
abstract class AbstractUtestModel {

	protected $controller;
	protected $name;
	protected $label;
	protected $result;

	public function __construct(UtestController $controller) {
		$this->controller = $controller;
		$this->name = preg_replace('/' . preg_quote('Model', '/') . '$/', '', get_class($this));
	}

	abstract function doTest();
	
	abstract protected function getRequestData($params);
	
	public function getTestName(){
		return $this->name;
	}
	
	public function getTestLabel(){
		return $this->label;
	}
	
	public function getTestTemplate(){
		return lcfirst($this->name . ".phtml");
	}
	
	public function  getTestResults(){
		return $this->result;
	}
}
