<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing user class
 *
 * @package  User
 * @since    0.5
 */
class Billrun_User {

	/**
	 * The user entity from users table
	 * @var Mongodloid_Entity
	 */
	protected $entity;

	/**
	 * constructor
	 * set the data instance
	 * 
	 * @param Mongodloid_Entity $entity The user entity from users table
	 */
	public function __construct($entity) {
		$this->entity = $entity;
	}

	/**
	 * Check if the user is allowed to perform an operation
	 * 
	 * http://php.net/manual/en/function.array-merge.php Example #3 array_merge() with non-array types
	 * @param string \ array $permission read/write/admin
	 * @return boolean
	 */
	public function allowed($permission, $page = null) {
		$permissions = array_merge((array)$permission, array('admin'));
		if (isset($this->entity['roles'][$page])) {
			return (boolean) array_intersect($this->entity['roles'][$page], $permissions);
		}
		return (boolean) array_intersect($this->entity['roles'], $permissions);
	}

	public function valid() {
		return !$this->entity->isEmpty();
	}

	public function getPermissions() {
		return  isset($this->entity['roles']) ? $this->entity['roles'] : array();
	}	
	
	public function getUsername() {
		return $this->entity['username'];
	}
	
	public function getMongoId($as_string = false) {
		if ($as_string) {
			return $this->entity['_id']->__toString();
		}
		return $this->entity['_id'];
	}
	
	public function getLastLogin() {
		return $this->entity['last_login'];
	}
	
}
