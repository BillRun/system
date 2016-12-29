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
class Billrun_Template_Token_Replacers_Collection extends Billrun_Template_Token_Replacers_Abstract {

	public function replaceTokens($string) {
		if(is_null($this->data)){
			return '[]';
		}
		$currency = Billrun_Factory::config()->getConfigValue('pricing.currency', '');
		switch ($string) {
			case 'debt':
				return "{$this->data['total']} {$currency}"; //TODO: convert curency code to symbols
			default: return '';
		}
	}

	protected function setAvailableTokens() {
		$this->availableTokens = array('debt');
	}

	protected function setCategory() {
		$this->category = 'collection';
	}

}
