<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing creditguard controller class
 *
 * @package  Controller
 * @since    4.0
 */
class CreditguardController extends ApiController {

	use Billrun_Traits_Api_Logger;

	/**
	 * method to set the available actions of the api from config declaration
	 */
	protected function setActions() {
		$this->actions = Billrun_Factory::config()->getConfigValue('creditguard.actions', array());
	}

	/**
	 * Get the source to log
	 * @return string
	 */
	protected function sourceToLog() {
		return "creditguard";
	}

}
