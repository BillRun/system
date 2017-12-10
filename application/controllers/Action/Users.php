<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * This class holds the api logic for the users.
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       4.0
 */
class UsersAction extends ApiAction {
	use Billrun_Traits_Api_UserPermissions;
	protected $_model;
	protected $_request;
	/**
	 * The logic to be executed when this API plugin is called.
	 */
	public function execute() {
		$this->allowed();
		Billrun_Factory::log("Execute users api call", Zend_Log::INFO);
		$this->_request = $this->getRequest();
		$this->_model = new UsersModel(array('sort' => array('provider' => 1, 'from' => 1)));
		switch($this->_request->get('action')){
			case 'read':
				$output = $this->getUser();
				break;
			case 'update':
				$output = ['Status' => $this->updateUser()];
				break;
			case 'delete':
				$output = ['Status' => $this->deleteUserById()];
				break;
			case 'insert':
				$output = ['Status' => $this->insertUser()];
				break;
			default:
				$output = ['Status' => 'Success', 'Body' => 'Missing action parameter'];
				break;
		}
		echo json_encode(['status' => 1,'details' => [$output]]);
		die();
	}
	
	protected function getUser(){
		if(!$userId = $this->_request->get('userId')){
			Billrun_Factory::log()->log("Missing parameter userId", Zend_Log::CRIT);
			$this->setError('Missing parameter userId', $this->_request);
			return true;
		}
		return $this->_model->getUserById($userId);
	}
	
	protected function updateUser(){
		if(!$userId = $this->_request->get('userId')){
			Billrun_Factory::log()->log("Missing parameter userId", Zend_Log::CRIT);
			$this->setError('Missing parameter userId', $this->_request);
			return true;
		}
		
		if(!$roles = json_decode($this->_request->get('roles'))){
			Billrun_Factory::log()->log("Missing parameter roles", Zend_Log::CRIT);
			$this->setError('Missing parameter roles', $this->_request);
			return true;
		}
		
		if(!$username = $this->_request->get('username')){
			Billrun_Factory::log()->log("Missing parameter username", Zend_Log::CRIT);
			$this->setError('Missing parameter roles', $this->_request);
			return true;
		}
		
		$password = $this->_request->get('password');
		
		return $this->_model->updateUser($userId, $username, $roles, $password);
	}
	
	protected function insertUser(){
		if(!$username = $this->_request->get('username')){
			Billrun_Factory::log()->log("Missing parameter username", Zend_Log::CRIT);
			$this->setError('Missing parameter username', $this->_request);
			return true;
		}
		
		if(!$password = $this->_request->get('password')){
			Billrun_Factory::log()->log("Missing parameter password", Zend_Log::CRIT);
			$this->setError('Missing parameter password', $this->_request);
			return true;
		}
		
		if(!$roles = json_decode($this->_request->get('roles'))){
			Billrun_Factory::log()->log("Missing parameter roles", Zend_Log::CRIT);
			$this->setError('Missing parameter roles', $this->_request);
			return true;
		}
		
		return $this->_model->insertUser($username, $roles, $password);
	}
	
	protected function deleteUserById(){
		if(!$userId = $this->_request->get('userId')){
			Billrun_Factory::log()->log("Missing parameter userId", Zend_Log::CRIT);
			$this->setError('Missing parameter userId', $this->_request);
			return true;
		}
		return $this->_model->deleteUserById($userId);
	}
	
	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_ADMIN;
	}
	
}
