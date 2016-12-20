<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * This class holds the api logic for the settings.
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       4.0
 */
class SettingsAction extends ApiAction {
	use Billrun_Traits_Api_UserPermissions;
	
	const PERMISSIONS_PATH = APPLICATION_PATH . "/conf/config/permissions.ini";
	
	protected $model;

	/**
	 * This method is for initializing the API Action's model.
	 */
	protected function initializeModel() {
		$this->model = new ConfigModel();
	}

	/**
	 * Enforce permissions on the settings API
	 * @param string $category - The requested category
	 * @param array $data - The requested data.
	 * @param string $action - Read or write.
	 */
	protected function enforcePermissions($category, $data, $action) {
		$categoryPermissionsFile = self::PERMISSIONS_PATH;

		$permissions = parse_ini_file($categoryPermissionsFile, true);
		if(!isset($permissions[$action])) {
			// TODO: Error code 3 suppose to mean 'invalid value'
			$invalidField = new Billrun_DataTypes_InvalidField("action", 3);
			throw new Billrun_Exceptions_InvalidFields(array($invalidField));
		}
		
		if(!$this->enforceCategoryPermissions($category, $data, $permissions[$action])) {
			$this->permissionLevel = $action;
			$this->allowed();
		}
	}
	
	/**
	 * Enforce the category 
	 * @param string $category - The name of the category.
	 * @param array $data - The input data
	 * @param array $permissions - The array of permissions
	 */
	protected function enforceCategoryPermissions($category, $data, &$permissions) {
		$enforced = false;
		if(!empty($permissions[$category])) {
			// Set the permission level to admin
			$this->permissionLevel = $permissions[$category];
			$this->allowed();
			$enforced = true;
		}

		// Check if the data contains more category keys
		if(Billrun_Util::isAssoc($data)) {
			foreach ($data as $key => $value) {
				$this->enforceCategoryPermissions($category . '.' . $key, $value, $permissions);
			}
		}
		
		return $enforced;
	}

		/**
	 * The logic to be executed when this API plugin is called.
	 */
	public function execute() {
		$request = $this->getRequest();
		$this->initializeModel();
		$category = $request->get('category');
		$rawData = $request->get('data');
		$data = json_decode($rawData, TRUE);
		if (json_last_error()) {
			$this->setError('Illegal data', $request->getPost());
			return TRUE;
		}
		if (!($category)) {
			$this->setError('Missing category parameter', $request->getPost());
			return TRUE;
		} 
		
		// TODO: Create action managers for the settings module.
		$action = $this->getActionValue($request);
		
		// Enforce permissions
		$this->enforcePermissions($category, $data, $action);
		
		// Forcing 'ROOT' to be an empty category name
		if($category === 'ROOT') {
			$category = "";
		}
		
		$success = true;
		$output = array();
		
		if ($action === 'set') {
			$success = $this->model->updateConfig($category, $data);
		} else if ($action === 'unset') {
			$success = $this->model->unsetFromConfig($category, $data);
		} else {
			$output = $this->model->getFromConfig($category, $data);
		}

		$this->getController()->setOutput(array(array(
				'status' => $success ? 1 : 0,
				'desc' => $success ? 'success' : 'error',
				'input' => $request->getPost(),
				'details' => is_bool($output)? array() : $output,
		)));
		return TRUE;
	}

	/**
	 * Get the action value from the request.
	 * @param type $request
	 * @return type
	 * @throws Billrun_Exceptions_InvalidFields
	 */
	protected function getActionValue($request) {
		$rawAction = $request->get('action');
		if(!is_string($rawAction)) {
			// TODO: The error code here (2) suppose to mean 'invalid type'
			$invalidField = new Billrun_DataTypes_InvalidField("action", 2);
			throw new Billrun_Exceptions_InvalidFields(array($invalidField));
		}
		
		// Return the last three characters
		return substr($rawAction, -3);
	}
	
	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_ADMIN;
	}

}
