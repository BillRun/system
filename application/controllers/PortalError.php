<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * controller handler for errors in customer portal
 *
 * @package  Controller
 * @since    5.14
 */
class PortalerrorController extends Yaf_Controller_Abstract {

	const NOT_FOUND_STATUS_CODE = 404;
	const UNAUTHENTICATED_STATUS_CODE = 401;
	const UNAUTHORIZED_STATUS_CODE = 403;

	public function notFoundAction() {
		$request = $this->getRequest();
		$response = $this->getResponse();
		$response->setHeader($request->getServer('SERVER_PROTOCOL'), self::NOT_FOUND_STATUS_CODE);
	}

	public function unauthenticatedAction() {
		$request = $this->getRequest();
		$response = $this->getResponse();
		$response->setHeader($request->getServer('SERVER_PROTOCOL'), self::UNAUTHENTICATED_STATUS_CODE);
	}

	protected function render($tpl, array $parameters = null) {
		return $this->getView()->render('api/index.phtml', $parameters);
	}

}
