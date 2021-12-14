<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Query.php';

/**
 * Aggregate query action class
 *
 * @package  Action
 * 
 * @since    2.8
 */
class QuerybillrunAction extends QueryAction {

	protected $type = 'querybillrun';

	/**
	 * The function to run before execute.
	 */
	protected function preExecute() {
		Billrun_Factory::log("Execute api query billrun", Zend_Log::INFO);
	}

	/**
	 * The function to run after execute.
	 */
	protected function postExecute() {
		Billrun_Factory::log("Query success", Zend_Log::INFO);
	}

	/**
	 * Get the array of fields that the request should have.
	 * @return array of field names.
	 */
	protected function getRequestFields() {
		return array('aid');
	}

	/**
	 * Sets additional values to the query.
	 * @param array $request Input array to set values by.
	 * @param array $query - Query to set values to.
	 */
	protected function setAdditionalValuesToQuery($request, $query) {
		if (isset($request['billrun'])) {
			$query['billrun_key'] = $this->getBillrunQuery($request['billrun']);
		}
	}

	/**
	 * Get the array of options to use for the query.
	 * @param array $request - Input request array.
	 * @return array Options array for the query.
	 */
	protected function getQueryOptions($request) {
		return array(
			'sort' => array('aid', 'billrun_key'),
//			@todo: support pagination
//			'page' => isset($request['page']) && $request['page'] > 0 ? (int) $request['page']: 0,
//			'size' =>isset($request['size']) && $request['size'] > 0 ? (int) $request['size']: 1000,
		);
	}

	/**
	 * Get the lines data by the input request and query.
	 * @param array $request - Input request array.
	 * @param array $linesRequestQueries - Array of queries to be parsed to get the lines data.
	 * @return array lines to return for the action.
	 */
	protected function getLinesData($request, $linesRequestQueries) {
		$params = array(
			'fetchParams' => $linesRequestQueries
		);

		return $this->getLinesDataForQuery($params);
	}

	/**
	 * basic fetch data method used by the cache
	 * 
	 * @param array $params parameters to fetch the data
	 * 
	 * @return boolean
	 */
	protected function fetchData($params) {
		$model = new BillrunModel($params['options']);
		$resource = $model->getData($params['find']);

		$results = array();
		foreach ($resource as $row) {
			$results[] = $row->getRawData();
		}
		return $results;
	}

}
