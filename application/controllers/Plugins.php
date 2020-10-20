<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing api controller class
 *
 * @package  Controller
 * @since    5.12
 */
class PluginsController extends Yaf_Controller_Abstract {

	use Billrun_Traits_Api_UserPermissions;

	protected $args = array();

	public function init() {
		$this->allowed();
		$this->args = array(
			'params' => $this->getRequest()->getParams(),
			'request' => $this->getRequest(),
			'response' => $this->getResponse(),
		);
	}

	/**
	 * method to trigger plugins action by api
	 */
	public function indexAction() {
		$trigger = preg_replace("/[^A-Za-z0-9]/", '', ucfirst(strtolower($this->args['params']['action'])));
		Billrun_Factory::dispatcher()->trigger('api' . $trigger, $this->args);
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_WRITE;
	}

	protected function render($tpl, array $parameters = null) {
		return $this->getView()->render('plugins/index.phtml', $parameters);
	}

}
