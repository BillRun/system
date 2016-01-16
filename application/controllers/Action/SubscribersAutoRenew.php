<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
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

		$manager = new Billrun_ActionManagers_APIManager($apiManagerInput);

		// This is the method which is going to be executed.
		return $manager->getAction();
	}

	/**
	 * The logic to be executed when this API plugin is called.
	 */
	public function execute() {
		Billrun_Factory::config()->addConfig(APPLICATION_PATH . '/conf/autorenew/conf.ini');
		// This is the method which is going to be executed.
		$action = $this->getAction();

		$output = "";
		// Check that received a valid action.
		if (is_string($action)) {
			// TODO: Report failed action. What do i write to the output if this happens?
			Billrun_Factory::log("Failed to get auto renew action instance for received input", Zend_Log::ALERT);

			$output = array(
				'status' => 0,
				'desc' => $action,
				'details' => 'Error'
			);
		} else {
			$output = $action->execute();

			// Set the raw input.
			$output['input'] = $this->getRequest()->getRequest();
		}
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

}
