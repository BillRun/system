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
abstract class Billrun_Template_Token_Tokens_Abstract {
	
	protected static $availableTokens;
	protected static $category;
	
	
	public function __construct() {
	  $this->setAvailableTokens();
	  $this->setCategory();
	}
	
	/**
	 * Get available placeholders
	 * @return Array
	 */
	public function getAvailableTokens() {
		return array($this->category => $this->availableTokens);
	}
	
	/**
	 * replace placeholder string
	 * @return String
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
