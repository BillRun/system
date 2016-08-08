<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Basic unit test model class
 *
 * @package  Models
 * @subpackage uTest
 * @since    4.0
 */
abstract class utest_AbstractUtestModel {

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

	public function getTestName() {
		return $this->name;
	}

	public function getTestLabel() {
		return $this->label;
	}

	public function getTestTemplate() {
		$prefix = 'utest_';
		$name = $this->name;
		if (substr($name, 0, strlen($prefix)) == $prefix) {
			$name = substr($name, strlen($prefix));
		}
		return lcfirst($name . ".phtml");
	}

	public function getTestResults() {
		return $this->result;
	}

}
