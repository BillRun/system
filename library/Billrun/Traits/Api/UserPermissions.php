<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

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
		if($this->authorizeUser($this->getPermissionLevel())) {
			return;
		}
		
		throw new Billrun_Exceptions_NoPermission();
	}
	
	/**
	 * method to check if user is authorize to resource
	 *
	 * @param string $permission the permission require authorization
	 *
	 * @return boolean true if have access, else false
	 *
	 * @todo: refactoring to core
	 * @todo Add the posibbility of authorizing per page, maybe using a type
	 * implementing the UserPermissions trait or creating a container for this trait
	 * with extended capabilities.
	 */
	protected function authorizeUser($permission) {
		$user = Billrun_Factory::user();
		if (!$user || !$user->valid()) {
			Billrun_Factory::log("Falied to get billrun user", Zend_Log::ERR);
			return false;
		}

		return $user->allowed($permission);
	}
}