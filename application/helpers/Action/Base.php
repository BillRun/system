<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing api controller class
 *
 * @package  Action
 * @since    0.5
 */
abstract class Action_Base extends Yaf_Action_Abstract {

	/**
	 * override the render method to use always the index tpl for all api calls
	 * 
	 * @param string $tpl the template
	 * @param array $parameters
	 * 
	 * @return string the output of the api
	 */
	protected function render($tpl, array $parameters = null) {
		return parent::render('index', $parameters);
	}

	protected function isOn() {
		if (Billrun_Factory::config()->getConfigValue($this->getRequest()->action)) {
			return true;
		}
		return false;
	}

}
