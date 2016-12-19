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
	 */
	protected function enforcePermissions($category, $data) {
		$categoryPermissionsFile = self::PERMISSIONS_PATH;

		$permissions = parse_ini_file($categoryPermissionsFile);
		return $this->enforceCategoryPermissions($category, $data, $permissions);
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
		
		// Enforce permissions
		$enforced = $this->enforcePermissions($category, $data);
		
		// Forcing 'ROOT' to be an empty category name
		if($category === 'ROOT') {
			$category = "";
		}
		// TODO: Create action managers for the settings module.
		$action = $request->get('action');
		$success = true;
		$output = array();
		
		// If permissions were not yet enforced, enforce them now.
		if(!$enforced) {
			$this->permissionLevel = Billrun_Traits_Api_IUserPermissions::PERMISSION_WRITE;
			if($action === 'get') {
				$this->permissionLevel = Billrun_Traits_Api_IUserPermissions::PERMISSION_READ;
			}
			$this->allowed();
		}
		
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

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_ADMIN;
	}

}
