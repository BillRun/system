<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

require_once APPLICATION_PATH . '/application/controllers/Externalpaypage.php';

/**
 * Billing paypage controller class
 *
 * @package  Controller
 * @since    5.0
 */

class InternalPaypageController extends ExternalPaypageController {
	use Billrun_Traits_Api_UserPermissions;

	public function init() {
		Billrun_Factory::db();
	}
	
	public function createAction() {
		$this->allowed();
		$request = $this->getRequest()->getRequest();
		$create = new Billrun_ActionManagers_Subscribers_Create();
		$type = empty($request['aid']) ? 'account' : 'subscriber';
		if (empty($request['aid']))
			unset($request['aid']);
		$query = array(
			"type" => $type,
			"subscriber" => json_encode($request)
		);
		$jsonObject = new Billrun_AnObj($query);
		if (!$create->parse($jsonObject)) {
			/* TODO: HANDLE ERROR! */
			return false;
		}
		if (!($res = $create->execute())) {
			/* TODO: HANDLE ERROR! */
			return false;
		}
		$passQuery = array("tenant" => Billrun_Factory::config()->getEnv());
		$creditGuardRow = Billrun_Factory::db()->creditGuardCollection()->query($passQuery)->cursor()->current();
		$secret = $creditGuardRow['bs'];
		$data = array(
			"aid" => $res['details']['aid'],
			"t" => time()
		);
		$hashResult = hash_hmac("sha512", json_encode($data), $secret);
		$sendData = array(
			"data" => $data,
			"signature" => $hashResult
		);

		$this->redirect('/api/creditguard', json_encode($sendData));
		return false;
	}
	
	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_ADMIN;
	}
}
