<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This Trait is used for API modules that handle additional input.
 *
 */
trait Billrun_Traits_Api_AdditionalInput {

	/**
	 * Comment for API action
	 * @var string or array
	 */
	protected $additional;

	protected function handleAdditional($input) {
		$this->additional = json_decode($input->get('additional'), true);
		if (!isset($this->additional)) {
			$this->additional = array();
		}
	}

}
