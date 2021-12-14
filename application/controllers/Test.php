<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing test controller class
 *
 * @package  Controller
 * @since    4.4
 */
class TestController extends Yaf_Controller_Abstract {

	use Billrun_Traits_Api_UserPermissions;

	public function init() {
		$this->allowed();
		if (Billrun_Factory::config()->isProd()) {
			die("Cannot run on production environment");
		}
		$request = $this->getRequest();
		$action = $this->getTestAction($request);
		Billrun_Test::getInstance($action);
		$this->getRequest()->action = 'index';
	}

	/**
	 * Empty index action to avoid exceptions
	 * @return none
	 */
	public function indexAction() {
		return;
	}

	protected function getTestAction($request) {
		// Get the URI of the request.
		$uri = $request->getRequestUri();

		// Explode the URI to get all the inputs
		$params = $this->escapeUri($uri);

		// Build the action.
		$action = $this->buildAction($params);

		return $action;
	}

	/**
	 * Returns an escaped array of paths built from the uri
	 * @param string $uri - Request URI.
	 * @return array An escaped array built from the request URI.
	 */
	protected function escapeUri($uri) {
		// Explode the URI to get all the inputs
		$params = explode('/', $uri);

		// The first parameter is empty, the second parameter is 'test'.
		$escapeIndex = 0;
		if (empty($params[$escapeIndex])) {
			unset($params[$escapeIndex]);
			$escapeIndex++;
		}

		if ($params[$escapeIndex] === 'test') {
			unset($params[$escapeIndex]);
		}

		return $params;
	}

	/**
	 * Build action from the escaped URI params.
	 * @param array $params - Escaped URI params.
	 * @return string Action name.
	 */
	protected function buildAction($params) {
		// Ucase all the input URIs
		$translated = array_map(function ($s) {
			return ucfirst(strtolower($s));
		}, $params);

		return implode("/", $translated);
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_READ;
	}

}
