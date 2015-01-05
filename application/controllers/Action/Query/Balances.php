<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Balances query action class
 *
 * @package  Action
 * 
 * @since    2.8
 */
class BalancesAction extends ApiAction {

	/**
	 * method to execute the query
	 * it's called automatically by the api main controller
	 */
	public function execute() {
		Billrun_Factory::log()->log("Execute api balances query", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests
		Billrun_Factory::log()->log("Input: " . print_R($request, 1), Zend_Log::DEBUG);

		if (!isset($request['aid']) && !isset($request['sid'])) {
			$this->setError('Require to supply aid or sid', $request);
			return true;
		}
		
		$find = array();
		$max_list = 10;
		
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
			$find['billrun_month'] = $this->getBillrunQuery($request['billrun']);
		}

		$cacheParams = array(
			'fetchParams' => array(
				'options' => array(),
				'find' => $find,
			),
		);

		$this->setCacheLifeTime(28800); // 8 hours
		$results = $this->cache($cacheParams);

		Billrun_Factory::log()->log("balances query success", Zend_Log::INFO);
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
		$model = new BalancesModel($params['options']);
		$results = $model->getData($params['find']);
		$ret = array();
		foreach($results as $row) {
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
	
	protected function getBillrunQuery($billrun) {
		return array('$in' => Billrun_Util::verify_array($this->getArrayParam($billrun), 'str'));
	}


}
