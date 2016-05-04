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
			$ret = json_decode($param, true);
		} else {
			$ret = (array) $param;
		}
		// convert short ref to real PHP MongoId (query convention)
		foreach ($ret as $k => &$v) {
			if (is_array($v) && isset($v['$id'])) {
				$v = new MongoId($v['$id']);
			}
		}
		return $ret;
	}
	
	protected function getBillrunQuery($billrun) {
		return array('$in' => Billrun_Util::verify_array($this->getArrayParam($billrun), 'str'));
	}


}
