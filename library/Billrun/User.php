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
	 * @param string $permission read/write/admin
	 * @return boolean
	 */
	public function allowed($permission, $page = null) {
		if (isset($this->entity['roles'][$page])) {
			return (boolean) array_intersect($this->entity['roles'][$page], array($permission, 'admin'));
		}
		return (boolean) array_intersect($this->entity['roles'], array($permission, 'admin'));
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
	
	public function getMongoId() {
		return $this->entity['_id'];
	}

}
