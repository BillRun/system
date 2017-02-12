<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi model for balance update
 *
 * @package  Billapi
 * @since    5.3
 */
abstract class Models_Balance_Update {

	protected $before;
	protected $after;

	public function __construct(array $params = array()) {
		// load balance update
	}

	abstract public function update();

	abstract function createLines();

	/**
	 * method to load the before state
	 */
	abstract protected function preload();

	public function preValidate() {
		return true;
	}

	public function postValidate() {
		return true;
	}

}
