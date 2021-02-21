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

	protected $model;

	/**
	 * This method is for initializing the API Action's model.
	 */
	protected function initializeModel() {
		$this->model = new ConfigModel();
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
		} else if ($category === 'ROOT') {
			$category = "";
		}
		
		$action = $request->get('action');
		$this->enforcePermissions($data, $category, $action);
		$success = true;
		$output = array();
		$warnings = array();
		if ($action === 'set') {
			$success = $this->model->updateConfig($category, $data);
			$warnings = $this->model->getWarnings($category, $data);
		} else if ($action === 'unset') {
			$success = $this->model->unsetFromConfig($category, $data);
		} else if ($action === 'validate') {
			$success = $this->model->validateConfig($category, $data);
		} else if ($action === 'enable') {
			$success = $this->model->setEnabled($category, $data, true);
		} else if ($action === 'disable') {
			$success = $this->model->setEnabled($category, $data, false);
		} else {
			$output = $this->model->getFromConfig($category, $data);
		}
		
		$status = $success ? (empty($warnings) ? 1 : 2) : 0;

		$this->getController()->setOutput(array( array(
				'status' => $status,
				'desc' => $success ? 'success' : 'error',
				'input' => $request->getPost(),
				'details' => is_bool($output) ? array() : $output,
				'warnings' => $warnings,
			)));
		return TRUE;
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_ADMIN; // this can be override by enforcePermissions method
	}
	
	/**
	 * method to enforce permissions, if applied by configuration
	 * 
	 * @param string $category category requested
	 * @param string $action action required to do (set, unset, get)
	 */
	protected function enforcePermissions($data, $category, $action) {
		$this->permissionLevel = Billrun_Traits_Api_IUserPermissions::PERMISSION_READ;
		
		if (empty($action)) {
			$action = 'get';
		} else if ($action == 'set' || $action == 'unset') {
			$this->permissionLevel = Billrun_Traits_Api_IUserPermissions::PERMISSION_ADMIN;
		}
		$config = Billrun_Factory::config();
		$config->addConfig(APPLICATION_PATH . "/conf/config/permissions.ini");
		$configPermissions = $config->getConfigValue('config.permissions');
		if (isset($configPermissions[$category])) {
			$this->permissionLevel = $configPermissions[$category];
		}
		$this->allowed();
	}
	
}