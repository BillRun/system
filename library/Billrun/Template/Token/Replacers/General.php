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
class Billrun_Template_Token_Replacers_General extends Billrun_Template_Token_Replacers_Abstract {

	public function replaceTokens($string) {
		switch ($string) {
			case 'current_time':
				return date("H:i");
			case 'curent_date':
				return date("d/m/Y");
			default:
				return '';
		}
	}

	protected function setAvailableTokens() {
		$this->availableTokens = array('current_time', 'curent_date');
	}

	protected function setCategory() {
		$this->category = 'general';
	}

}
