<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
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
	public function allowed($permission) {
		return (boolean) array_intersect($this->entity['roles'], array($permission, 'admin'));
	}
	
	public function valid() {
		return !$this->entity->isEmpty();
	}

}
