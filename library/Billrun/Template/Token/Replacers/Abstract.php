<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class .
 *
 * @package  
 * @since    5.2
 * @todo Create unit tests for this module
 */
abstract class Billrun_Template_Token_Replacers_Abstract {

	protected $availableTokens;
	protected $category;
	protected $data;

	public function __construct() {
		$this->setAvailableTokens();
		$this->setCategory();
	}

	/**
	 * Set class class data to replace
	 */
	public function setData($data = null) {
		$this->data = $data;
	}

	/**
	 * Get available replacer tokens
	 * @return Array
	 */
	public function getAvailableTokens() {
		return $this->availableTokens;
	}

	/**
	 * Replace placeholder in string
	 */
	abstract public function replaceTokens($string);

	/**
	 * Set Available class tokens
	 */
	abstract protected function setAvailableTokens();

	/**
	 * Set class category name
	 */
	abstract protected function setCategory();
}
