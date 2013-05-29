<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
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
	public function render($tpl, array $parameters = null) {
		$tpl = 'index';
		return parent::render($tpl, $parameters);
	}
	
}