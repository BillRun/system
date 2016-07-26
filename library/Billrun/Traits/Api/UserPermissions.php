<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

const PERMISSION_READ = "read";
const PERMISSION_WRITE = "write";
const PERMISSION_ADMIN = "admin";

/**
 * This Trait is used for API modules that handle additional input.
 *
 */
trait Billrun_Traits_Api_UserPermissions {

	protected abstract function getPermissionLevel();
	
	/**
	 * method to check if user is allowed to access page, if not redirect or show error message
	 *
	 * @param string $permission the permission required to the page
	 *
	 * @return boolean true if have access, else false
	 *
	 */
	protected function allowed() {
		return $this->authorizeUser($this->getPermissionLevel());
	}
	
	/**
	 * method to check if user is authorize to resource
	 *
	 * @param string $permission the permission require authorization
	 *
	 * @return boolean true if have access, else false
	 *
	 * @todo: refactoring to core
	 */
	protected function authorizeUser($permission) {
		$user = Billrun_Factory::user();
		if (!$user || !$user->valid()) {
			Billrun_Factory::log("Falied to get billrun user", Zend_Log::ERR);
			return false;
		}

		return $user->allowed($permission);
	}

	protected function init23() {
		if($this->allowed()) {
			return;
		}
		
		$error = "No permission to execute this action";
		Billrun_Factory::log($error, Zend_Log::NOTICE);
		throw new Exception($error);
	}
}