<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This trait is used to hold the input which prompted an exception.
 *
 * @package  Exceptions
 * @since    5.2
 */
trait Billrun_Exceptions_InputContainer {
	/**
	 * The input which prompted the error.
	 * @var mixed 
	 */
	protected $input;
	
	public function setInput($input) {
		$this->input = $input;
	} 
}
