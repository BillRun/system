<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Rates action class
 *
 * @package  Action
 * 
 * @since    2.6
 */
class RatesAction extends Billrun_API_CompoundParamAction {

	protected $model;

	/**
	 * Process the query and prepere it for usage by the Plans model
	 * @param type $query the query that was recevied from the http request after being
	 * proccessed in the getCompoundParams function.
	 * @return array containing the processed query.
	 */
	protected function processQuery($query) {
		$matches = preg_grep('/rates.\w+.plans/', array_keys($query));
		foreach($matches as $m) {
			$query[$m] = $this->model->getPlan($query[$m]);
		}
		
		return parent::processQuery($query);
	}
	
	/**
	 * Fetch results from the related model using the fetch data params.
	 * @param array $params - Fetch data params.
	 * @return array of results from the model.
	 */
	protected function fetchDataFromModel($params) {
		return $this->model->getData($params['query'], $params['filter']);
	}

	/**
	 * This function is called prior to the execute function.
	 */
	protected function preExecute() {
		Billrun_Factory::log("Execute rates api call", Zend_Log::INFO);
		$this->model = new RatesModel(array('sort' => array('provider' => 1, 'from' => 1)));
	}

}