<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
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
		$this->allowed();
		$request = $this->getRequest();
		try {
			$this->initializeModel();
			$category = $request->get('category');
			$data = $request->get('data');
			$data = json_decode($data, TRUE);
			if (json_last_error() || !is_array($data)) {
				$this->setError('No data to update or illegal data array', $request->getPost());
				return TRUE;
			}
			if (!($category)) {
				$this->setError('Missing category parameter', $request->getPost());
				return TRUE;
			}
			$action = $request->get('action');
			if ($action === 'set') {
				$output = $this->model->updateConfig($category, $data);
			} else if ($action === 'unset') {
				$output = $this->model->unsetFromConfig($category, $data);
			} else {
				$output = $this->model->getFromConfig($category, $data);
			}
			$this->getController()->setOutput(array(array(
					'status' => $output ? 1 : 0,
					'desc' => $output ? 'success' : 'error',
					'input' => $request->getPost(),
					'details' => is_bool($output)? array() : $output,
			)));
		} catch (Exception $ex) {
			$this->setError($ex->getMessage(), $request->getPost());
			return TRUE;
		}
		return TRUE;
	}

	protected function getPermissionLevel() {
		return PERMISSION_WRITE;
	}

}
