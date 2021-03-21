<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Balances query action class
 *
 * @package  Action
 * 
 * @since    2.8
 * 
 * @deprecated since version 4.0
 */
class BalancesAction extends QueryAction {
	protected $type = 'balances';
	/**
	 * Get the max list count.
	 * @return int The maximum number allowed for the query.
	 */
	protected function getMaxList() {
		return 10;
	}

	/**
	 * The function to run before execute.
	 */
	protected function preExecute() {
		Billrun_Factory::log("Execute api balances query", Zend_Log::INFO);
	}

	/**
	 * The function to run after execute.
	 */
	protected function postExecute() {
		Billrun_Factory::log("balances query success", Zend_Log::INFO);
	}

	/**
	 * Sets additional values to the query.
	 * @param array $request Input array to set values by.
	 * @param array $query - Query to set values to.
	 */
	protected function setAdditionalValuesToQuery($request, $query) {
		if (isset($request['billrun'])) {
			$query['billrun_month'] = $this->getBillrunQuery($request['billrun']);
		}
	}

	/**
	 * Get the array of options to use for the query.
	 * @param array $request - Input request array.
	 * @return array Options array for the query.
	 */
	protected function getQueryOptions($request) {
		return array();
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
		$model = new BalancesModel($params['options']);
		$results = $model->getData($params['find']);
		$ret = array();
		foreach ($results as $row) {
			$ret[] = $row->getRawData();
		}
		return $ret;
	}

	/**
	 * method to retreive variable in dual way json or pure array
	 * 
	 * @param mixed $param the param to retreive
	 */
	protected function getArrayParam($param) {
		if (empty($param)) {
			return array();
		}
		if (is_string($param)) {
			return json_decode($param, true);
		}
		return (array) $param;
	}

}
