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
abstract class UtestModel {

	protected $controller;

	public function __construct(UtestController $controller) {
		$this->controller = $controller;
	}

	abstract function doTest();

	abstract protected function getRequestData($params);
}
