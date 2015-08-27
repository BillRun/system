<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Plans action class
 *
 * @package  Action
 * 
 * @since    2.6
 */
class PlansAction extends Billrun_API_CompoundParamAction {
	/**
	 * Return data from received resource.
	 * @param Mongodloid_Cursor $resource - Resource to extract data from.
	 * @return array Array of results.
	 */
	protected function getModelDataFromResource($resource) {
		$results = null;
		if (is_resource($resource)) {
			$results = iterator_to_array($resource);
		} else if ($resource instanceof Mongodloid_Cursor) {
			$results = array();
			foreach ($resource as $item) {
				$results[] = $item->getRawData();
			}
		}
		
		return $results;
	}
	
	/**
	 * Fetch results from the related model using the fetch data params.
	 * @param array $params - Fetch data params.
	 * @return array of results from the model.
	 */
	protected function fetchDataFromModel($params) {
		$model = new PlansModel(array('sort' => array('from' => 1)));
		$resource = $model->getData($params['query'], $params['filter']);
		
		return $this->getModelDataFromResource($resource);
	}

	/**
	 * This function is called prior to the execute function.
	 */
	protected function preExecute() {
		Billrun_Factory::log("Execute plans api call", Zend_Log::INFO);
	}

}