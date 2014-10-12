<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Query action class
 *
 * @package  Action
 * 
 * @since    2.6
 */
class QueryAction extends ApiAction {

	/**
	 * method to execute the query
	 * it's called automatically by the api main controller
	 */
	public function execute() {
		Billrun_Factory::log()->log("Execute api query", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests
		Billrun_Factory::log()->log("Input: " . print_R($request, 1), Zend_Log::INFO);

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
			$find['billrun'] = (string) $request['billrun'];
		}

		if (isset($request['query'])) {
			if (is_string($request['query'])) {
				$query = json_decode($request['query'], true);
			} else {
				$query = (array) $request['query'];
			}
			$find = array_merge($find, (array) $query);
		}

		$options = array(
			'sort' => array('urt'),
			'page' => isset($request['page']) && $request['page'] > 0 ? (int) $request['page']: 0,
			'size' =>isset($request['size']) && $request['size'] > 0 ? (int) $request['size']: 1000,
		);
		$model = new LinesModel($options);
		
		if (isset($request['distinct'])) {
			$lines = $model->getDistinctField((string) $request['distinct'], $find);
		} else {
			$lines = $model->getData($find);

			foreach ($lines as &$line) {
				$line = $line->getRawData();
			}
		}

		Billrun_Factory::log()->log("query success", Zend_Log::INFO);
		$ret = array(
			array(
				'status' => 1,
				'desc' => 'success',
				'input' => $request,
				'details' => $lines,
			)
		);
		$this->getController()->setOutput($ret);
	}

}
