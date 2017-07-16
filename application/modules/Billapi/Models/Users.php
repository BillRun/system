<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2017 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi users model for user entity
 *
 * @package  Billapi
 * @since    5.6
 */
class Models_Users extends Models_Entity {

	public function delete() {
		$usersColl = Billrun_Factory::db()->usersCollection();
		$userToDelete = $usersColl->query($this->query)->cursor()->current();
		$this->checkIfLastAdmin($usersColl, $userToDelete);
		$loggedUserName = Billrun_Factory::user()->getUsername();
		if ($loggedUserName == $userToDelete['username']) {
			throw new Exception("Can't delete current user");
		}

		parent::delete();
	}
	
	public function update() {
		$usersColl = Billrun_Factory::db()->usersCollection();
		$userToDelete = $usersColl->query($this->query)->cursor()->current();
		$this->checkIfLastAdmin($usersColl, $userToDelete);
		parent::update();
	}
	
	protected function checkIfLastAdmin($usersColl, $userToDelete) {
		$adminQuery = array(
			'roles' => array(
				'$in' => array("admin")
			),
		);
		
		$numOfAdmin = $usersColl->query($adminQuery)->cursor()->count();
		if ($numOfAdmin <= 1 && $this->getOverrideAdminCondition($userToDelete)) {
			throw new Exception("Can't delete the last active admin user");
		}
	}
	
	protected function getOverrideAdminCondition($userToDelete) {
		if ($this->action == 'update') {
			return in_array('admin', array_diff($userToDelete['roles'], $this->update['roles']));
		} else if ($this->action == 'delete') {
			return in_array('admin', $userToDelete['roles']);
		}
	}
	
}
