<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Users model class
 *
 * @package  Models
 * @subpackage Table
 * @since    0.5
 */
class UsersModel extends TableModel {
	
	const ROLES = ['admin', 'read', 'write'];

	public function __construct(array $params = array()) {
		$params['collection'] = Billrun_Factory::db()->users;
		parent::__construct($params);
		$this->search_key = "username";
	}
	
	public function getUserById($userId){
		$mongoId = new Mongodloid_Id($userId);
		Billrun_Factory::log("Finish get user by id", Zend_Log::INFO);
		return current(iterator_to_array($this->collection->query(['_id' => $mongoId])->cursor()))->getRawData();
	}
	
	public function deleteUserById($userId){
		try{
			$mongoId = new Mongodloid_Id($userId);
			$deleteQuery = $this->collection->remove(['_id' => $mongoId]);
			Billrun_Factory::log("Finish remove user", Zend_Log::INFO);
		}catch(\MongoException $e){
			return $this->reportError($e->getMessage(), Zend_Log::NOTICE);
		}
		return "{$deleteQuery['nModified']} rows been removed";
	}
	
	public function insertUser($username, $roles, $password){
		try{ 
			$password = password_hash($password, PASSWORD_DEFAULT);
			$checkForExistUsername = current(iterator_to_array($this->collection->query(['username' => $username])->cursor()));
			
			if($checkForExistUsername){
				Billrun_Factory::log("Username already exist {$username}", Zend_Log::INFO);
				throw $ex = new Billrun_Exceptions_Api(0, array(), 'Username already exist');
			}
			$userData = ['username' => $username, 'password' => $password, 'roles' => $roles];
			$insertQuery = $this->collection->insert($userData);
			Billrun_Factory::log("Finish insert new user", Zend_Log::INFO);
		}catch(\MongoException $e){
			Billrun_Factory::log()->log($e->getMessage(), Zend_Log::CRIT);
		}
			return "{$insertQuery['nModified']} rows been inserted";
	}
	
	public function updateUser($userId, $username, $roles, $password){
		$mongoId = new Mongodloid_Id($userId);
		$setArray = array('username' => (string) $username,'roles' => $roles );
		
		foreach($roles as $role){
			if(!in_array($role, self::ROLES)){
				Billrun_Factory::log()->log("Illegal roles entered", Zend_Log::CRIT);
				return "Illegal roles";
			}
		}
		
		if($password){
			$password = password_hash($password, PASSWORD_DEFAULT);
			$setArray['password'] = $password;
		}
		
		try{
			Billrun_Factory::log("Start Update {$setArray}", Zend_Log::INFO);
			$updateQuery = $this->collection->update(array('_id' => $mongoId), array('$set' => $setArray));
		}catch(\MongoException $e){
			Billrun_Factory::log()->log($e->getMessage(), Zend_Log::CRIT);
		}
			return "{$updateQuery['nModified']} rows been modified";
	}
	
	public function updateUserLastLogin($userId){
		$mongoId =  new Mongodloid_Id($userId);
		$setArray = array('last_login' => new Mongodloid_Date());
		
		try{
			Billrun_Factory::log("Start Update user last login : " . print_r($setArray, 1), Zend_Log::INFO);
			$updateQuery = $this->collection->update(array('_id' => $mongoId), array('$set' => $setArray));
		}catch(\MongoException $e){
			Billrun_Factory::log()->log($e->getMessage(), Zend_Log::CRIT);
		}
		return "{$updateQuery['nModified']} rows been modified";
	}
	
	public function getFilterFields() {
		$filter_fields = array(
			'username' => array(
				'key' => 'username',
				'db_key' => 'username',
				'input_type' => 'text',
				'comparison' => 'contains',
				'display' => 'Username',
				'default' => '',
			),
		);
		return array_merge($filter_fields, parent::getFilterFields());
	}

	public function getFilterFieldsOrder() {
		$filter_field_order = array(
			0 => array(
				'username' => array(
					'width' => 2,
				),
			),
		);
		return $filter_field_order;
	}

	public function getTableColumns() {
		$columns = array(
			'username' => 'Username',
			'roles' => 'Roles',
		);
		return $columns;
	}

	public function update($params) {
		if (isset($params['password'])) {
			if (!password_get_info($params['password'])['algo']) {
				$params['password'] = password_hash($params['password'], PASSWORD_DEFAULT);
			}
		}
		return parent::update($params);
	}

	public function getEmptyItem() {
		return new Mongodloid_Entity(array(
			'username' => '',
			'password' => '',
			'roles' => array(
			),
		));
	}

	public function getData($filter_query = array()) {
		$resource = parent::getData();
		$ret = array();
		foreach ($resource as $item) {
			$item['roles'] = json_encode($item['roles']);
			$ret[] = $item;
		}
		return $ret;
	}

}
