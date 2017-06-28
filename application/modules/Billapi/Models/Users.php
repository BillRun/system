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
 * @since    5.3
 */
class Models_Users extends Models_Entity {

	public function delete() {
		$adminQuery = array(
			'roles' => array(
				'$in' => array("admin")
			),
		);
		$usersColl = Billrun_Factory::db()->usersCollection();
		$numOfAdmin = $usersColl->query($adminQuery)->cursor()->count();
		if ($numOfAdmin <= 1) {
			throw new Exception("Can't delete the last active admin user");
		}
		$loggedUserName = Billrun_Factory::user()->getUsername();
		$userToDelete = $usersColl->query($this->query)->cursor()->current();
		if ($loggedUserName == $userToDelete['username']) {
			throw new Exception("Can't delete current user");
		}

		parent::delete();
	}

}
