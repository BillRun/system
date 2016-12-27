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
class Billrun_Template_Token_Replacers_Account extends Billrun_Template_Token_Replacers_Abstract {
	
	public function replaceTokens($string) {
		if(is_null($this->data)){
			return '[]';
		}
		switch ($string) {
			case 'last_name':
				return $this->data->lastname;
			case 'first_name':
				return $this->data->firstname;
			default:
				return '';
		}
	}

	protected function setAvailableTokens() {
		$this->availableTokens = array('last_name', 'first_name');
	}

	protected function setCategory() {
		$this->category = 'account';
	}

}
