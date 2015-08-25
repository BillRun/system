<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * This class holds the api logic for the subscribers.
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       4.0
*/
class SubscribersAction extends ApiAction {
	
	protected $model;
	
	/**
	 * The logic to be executed when this API plugin is called.
	 */
	public function execute() {
		$this->model = new SubscribersModel(array('sort' => array('from' => 1)));
		
		$request = $this->getRequest();

		// This is the method which is going to be executed.
		$action = Billrun_ActionManagers_Manager::getAction($request, 'Subscribers');
		
		// Check that received a valid action.
		if(!$action) {
			// TODO: Report failed action. What do i write to the output if this happens?
			Billrun_Factory::log("Failed to get subscriber action instance for received input", Zend_Log::ALERT);
			return;
		}
		
		$output = $action->execute();
		
		// Set the raw input.
		$output['input'] = $request->getRequest();
		
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
		$model = new SubscribersModel($params['options']);
		$results = $model->getData($params['find']);
		$ret = array();
		foreach($results as $row) {
			$ret[] = $row->getRawData();
		}
		return $ret;
	}
}
