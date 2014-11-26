<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
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

	/**
	 * method to execute the query
	 * it's called automatically by the api main controller
	 */
	public function execute() {
		Billrun_Factory::log()->log("Execute api query aggregate", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests
		Billrun_Factory::log()->log("Input: " . print_R($request, 1), Zend_Log::DEBUG);

		if (!isset($request['aid']) && !isset($request['sid'])) {
			$this->setError('Require to supply aid or sid', $request);
			return true;
		}
		
		$find = array();
		$max_list = 1000;
		
		if (isset($request['aid'])) {
			$aids = Billrun_Util::verify_array($request['aid'], 'int');
			if (count($aids) > $max_list) {
				$this->setError('Maximum of aid is ' . $max_list, $request);
				return true;
			}
			$find['aid'] = array('$in' => $aids);
		}

		if (isset($request['sid'])) {
			$sids = Billrun_Util::verify_array($request['sid'], 'int');
			if (count($sids) > $max_list) {
				$this->setError('Maximum of sid is ' . $max_list, $request);
				return true;
			}
			$find['sid'] = array('$in' => $sids);
		}

		if (isset($request['billrun'])) {
			$find['billrun'] = $this->getBillrunQuery($request['billrun']);
		}

		if (isset($request['query'])) {
			$query = $this->getArrayParam($request['query']);
			$find = array_merge($find, (array) $query);
		}
		
		if (isset($request['groupby'])) {
			$groupby = array('_id' => $this->getArrayParam($request['groupby']));
		} else {
			$groupby = array('_id' => null);
		}

		if (isset($request['aggregate'])) {
			$aggregate = $this->getArrayParam($request['aggregate']);
		} else {
			$aggregate = array('count' => array('$sum' => 1));
		}
		
		$group = array_merge($groupby, $aggregate);

		$options = array(
			'sort' => array('urt'),
			'page' => isset($request['page']) && $request['page'] > 0 ? (int) $request['page']: 0,
			'size' =>isset($request['size']) && $request['size'] > 0 ? (int) $request['size']: 1000,
		);
		
		$cacheParams = array(
			'fetchParams' => array(
				'options' => $options,
				'find' => $find,
				'group' => $group,
				'groupby' => $groupby,
			),
		);

		$this->setCacheLifeTime(604800); // 1 week
		$results = $this->cache($cacheParams);
		Billrun_Factory::log()->log("Aggregate query success", Zend_Log::INFO);
		$ret = array(
			array(
				'status' => 1,
				'desc' => 'success',
				'input' => $request,
				'details' => $results,
			)
		);
		$this->getController()->setOutput($ret);
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
		if (isset($params['groupby']['_id'])) {
			$groupby_keys = array_reverse(array_keys($params['groupby']['_id']));
		} else {
			$groupby_keys = array();
		}
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
