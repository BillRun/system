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
class QueryaggregateAction extends QueryAction {

	protected $type = 'queryaggregate';

	/**
	 * The function to run before execute.
	 */
	protected function preExecute() {
		Billrun_Factory::log("Execute api query aggregate", Zend_Log::INFO);
	}

	/**
	 * The function to run after execute.
	 */
	protected function postExecute() {
		Billrun_Factory::log("Aggregate query success", Zend_Log::INFO);
	}

	/**
	 * Return the group by filter for the query.
	 * @param array $request - User request to parse.
	 * @return array Query to filter the lines by group.
	 */
	protected function getGroupBy($request) {
		if (isset($request['groupby'])) {
			$groupby = array('_id' => $this->getArrayParam($request['groupby']));
		} else {
			$groupby = array('_id' => null);
		}

		return $groupby;
	}

	/**
	 * Return the aggregate filter for the query.
	 * @param array $request - User request to parse.
	 * @return array Query to aggreagate the lines (count).
	 */
	protected function getAggregate($request) {
		if (isset($request['aggregate'])) {
			$aggregate = $this->getArrayParam($request['aggregate']);
		} else {
			$aggregate = array('count' => array('$sum' => 1));
		}

		return $aggregate;
	}

	/**
	 * Get the lines data by the input request and query.
	 * @param array $request - Input request array.
	 * @param array $linesRequestQueries - Array of queries to be parsed to get the lines data.
	 * @return array lines to return for the action.
	 */
	protected function getLinesData($request, $linesRequestQueries) {
		$groupBy = $this->getGroupBy($request);
		$aggregate = $this->getAggregate($request);
		$group = array_merge($groupBy, $aggregate);

		$linesRequestQueries['group'] = $group;
		$linesRequestQueries['groupby'] = $groupBy;
		$params = array(
			'fetchParams' => $linesRequestQueries
		);

		return $this->getLinesDataForQuery($params);
	}

	/**
	 * Get the groupby keys by the input pararmters.
	 * @param array $params - Input paramters to get the keys by.
	 * @return array of group by keys.
	 */
	protected function getGroupbyKeys($params) {
		if (!isset($params['groupby']['_id'])) {
			return array();
		}

		return array_reverse(array_keys($params['groupby']['_id']));
	}

	/**
	 * basic fetch data method used by the cache
	 * 
	 * @param array $params parameters to fetch the data
	 * 
	 * @return boolean
	 */
	protected function fetchData($params) {
		$model = new LinesModel($params['options']);
		$lines = $model->getDataAggregated(array('$match' => $params['find']), array('$group' => $params['group']));
		$groupby_keys = $this->getGroupbyKeys($params);

		$results = array();
		foreach ($lines as $line) {
			$row = $line->getRawData();
			foreach ($groupby_keys as $key) {
				$row[$key] = $row['_id'][$key];
			}
			unset($row['_id']);
			$results[] = array_reverse($row, true);
		}
		return $results;
	}

}
