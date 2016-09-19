<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * This class holds the api logic for the auto renew.
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       4.0
 */
class SubscribersAutoRenewAction extends ApiAction {
	use Billrun_Traits_Api_UserPermissions;
	
	protected $model;

	/**
	 * Get the correct action to use for this request.
	 * @return Billrun_ActionManagers_Action
	 * @todo - This is a generic function should find a better place to put it.
	 */
	protected function getAction() {
		$apiName = str_replace("Action", "", __CLASS__);
		$apiManagerInput = array(
			'input' => $this->getRequest(),
			'api_name' => $apiName
		);

		$this->manager = new Billrun_ActionManagers_APIManager($apiManagerInput);

		// This is the method which is going to be executed.
		return $this->manager->getAction();
	}

	/**
	 * The logic to be executed when this API plugin is called.
	 */
	public function execute() {
		// Check if allowed.
		$this->allowed();
		
		Billrun_Factory::config()->addConfig(APPLICATION_PATH . '/conf/autorenew/conf.ini');
		// This is the method which is going to be executed.
		$action = $this->getAction();

		$output = $action->execute();

		// Set the raw input.
		$output['input'] = $this->getRequest()->getRequest();
		$this->getController()->setOutput(array($output));
	}

	/**
	 * basic fetch data method used by the cache
	 * 
	 * @param array $params parameters to fetch the data
	 * 
	 * @return boolean
	 */
	protected function fetchData($params) {
		$model = new SubscribersautorenewservicesModel($params['options']);
		$results = $model->getData($params['find']);
		$ret = array();
		foreach ($results as $row) {
			$ret[] = $row->getRawData();
		}
		return $ret;
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_WRITE;
	}

}
