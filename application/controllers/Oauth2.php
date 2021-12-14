<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
//require_once(APPLICATION_PATH . '/library/OAuth2/Autoloader.php');

/**
 * Billing oauth2 controller
 * 
 * 
 * @package  Controller
 * @since    5.13
 */
class Oauth2Controller extends ApiController {

	/**
	 * no need for indexAction
	 */
	public function indexAction() {
		$this->forward('authorize');
	}

	/**
	 * entry-point to receive oauth2 access token
	 */
	public function tokenAction() {
		$this->getView()->response = Billrun_Factory::oauth2()->handleTokenRequest(OAuth2\Request::createFromGlobals());
	}

}
