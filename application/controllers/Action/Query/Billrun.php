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
class QuerybillrunAction extends QueryAction {

	/**
	 * method to execute the query
	 * it's called automatically by the api main controller
	 */
	public function execute() {
		Billrun_Factory::log()->log("Execute api query billrun", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests
		Billrun_Factory::log()->log("Input: " . print_R($request, 1), Zend_Log::INFO);

		if (!isset($request['aid'])) {
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

		if (isset($request['billrun'])) {
			$find['billrun_key'] = array('$in' => Billrun_Util::verify_array($request['billrun'], 'str'));
		}

		$options = array(
			'sort' => array('aid', 'billrun_key'),
//			@todo: support pagination
//			'page' => isset($request['page']) && $request['page'] > 0 ? (int) $request['page']: 0,
//			'size' =>isset($request['size']) && $request['size'] > 0 ? (int) $request['size']: 1000,
		);
		
		$model = new BillrunModel($options);
		$resource = $model->getData(array());

		$results = array();
		foreach ($resource as $row) {
			$results[] = $row->getRawData();
		}

		Billrun_Factory::log()->log("query success", Zend_Log::INFO);
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

}
