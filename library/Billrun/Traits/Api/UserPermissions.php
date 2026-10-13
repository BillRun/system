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

	protected $permissionLevel = null;
	
	protected abstract function getPermissionLevel();
	
	private function permissionValue() {
		if($this->permissionLevel === null) {
			return $this->getPermissionLevel();
		}
		
		return $this->permissionLevel;
	}
	
	/**
	 * Function used to get the data to validate
	 * @todo: This is just because the allowed function used to be without any
	 * parameters, when we will refactor remove this function.
	 * @return array
	 */
	protected function getData() {
		return $this->getRequest()->getRequest();
	}
	
	/**
	 * method to check if user is allowed to access page, if not redirect or show error message
	 *
	 * @param array $input the data to validate.
	 *
	 * @return boolean true if have access, else false
	 *
	 */
	protected function allowed(array $input = array()) {
		if(!$input) {
			$input = $this->getData();
		}
		
		// Try to validate using the new cryptological protected method
		if(Billrun_Utils_Security::validateData($input)) {
			return;
		}
		
		// TODO: This should be removed after all api access uses the signature technique
		if($this->authorizeUser($this->permissionValue())) {
			return;
		}
		
		throw new Billrun_Exceptions_NoPermission();
	}
	
	/**
	 * method to check if user is authorize to resource
	 *
	 * @param string/array $permission the permission require authorization
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
			Billrun_Factory::log("Failed to get billrun user", Zend_Log::INFO);
			return false;
		}

		return $user->allowed($permission);
	}
}