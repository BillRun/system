<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
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

	const MAX_LIST = 1000;
	
	/**
	 * Get the count limit query part.
	 * @param array $request - Input request.
	 * @return boolean Array of queries according to received max size, false if invalid.
	 */
	protected function getMaxListQuery($request) {
		$returnQuery = array();
		
		$fieldsToCheck = array('aid', 'sid');
		
		foreach ($fieldsToCheck as $field) {
			if (!isset($request[$field])) {
				continue;
			}
			
			$queryLimitResults = $this->getQueryLimitForField($request, $field);

			// Error occured.
			if($queryLimitResults === false) {
				return false;
			}

			$returnQuery[$field] = $queryLimitResults;
		}
		
		return $returnQuery;
	}
	
	/**
	 * Get the query for limiting results of list.
	 * @param array $request - Input request.
	 * @param string $paramName - Param name to limit by.
	 * @return array - Query for limiting, or false if error occured.
	 */
	protected function getQueryLimitForField($request, $paramName) {
		$verifiedArray = Billrun_Util::verify_array($request[$paramName], 'int');
		if (count($verifiedArray) > self::MAX_LIST) {
			$this->setError('Maximum of '. $paramName . ' is ' . self::MAX_LIST, $request);
			return false;
		}
		
		return array('$in' => $verifiedArray);
	}
	
	/**
	 * Build the query for the api exectie based on the input request.
	 * @param array $request - Input request array.
	 * @return array The array to use for the query execute, false if error occured.
	 */
	protected function buildQuery($request) {
		$executeQuery = $this->getMaxListQuery($request);
		// Error occured.
		if($executeQuery === false) {
			// TODO: Return true on purpose? 
			return false;
		}
		
		if (isset($request['billrun'])) {
			$executeQuery['billrun'] = $this->getBillrunQuery($request['billrun']);
		}

		if (isset($request['query'])) {
			$inputRequestQuery = $this->getArrayParam($request['query']);
			$executeQuery = array_merge($executeQuery, (array) $inputRequestQuery);
		}
		
		return $executeQuery;
	}
	
	/**
	 * Get all the lines for the input query.
	 * @param array $request - Input request array.
	 * @return array of lines to return as result, false if error occurred.
	 */
	protected function getResultLines($request) {
		$executeQuery = $this->buildQuery($request);
		// Error occured.
		if($executeQuery === false) {
			// TODO: Return true on purpose? 
			return false;
		}
		
		$model = new LinesModel($this->getQueryOptions($request));
		$lines = null;
		if (isset($request['distinct'])) {
			$lines = $model->getDistinctField((string) $request['distinct'], $executeQuery);
		} else {
			$lines = $model->getData($executeQuery);
			foreach ($lines as &$line) {
				$line = $line->getRawData();
			}
		}
		
		return $lines;
	}
	
	/**
	 * Get the array of options to use for the query.
	 * @param array $request - Input request array.
	 * @return array Options array for the query.
	 */
	protected function getQueryOptions($request) {
		return array(
			'sort' => array('urt'),
			'page' => isset($request['page']) && $request['page'] > 0 ? (int) $request['page']: 0,
			'size' =>isset($request['size']) && $request['size'] > 0 ? (int) $request['size']: 1000,
			);
	}
	
	/**
	 * Send the results to the controller.
	 * @param array $request - Input array request.
	 * @param array $result - Array of results for the requested query.
	 */
	protected function sendResults($request, $result) {
		$ret = array(
			array(
				'status' => 1,
				'desc' => 'success',
				'input' => $request,
				'details' => $result,
			)
		);
		$this->getController()->setOutput($ret);
	}
	
	/**
	 * method to execute the query
	 * it's called automatically by the api main controller
	 */
	public function execute() {
		Billrun_Factory::log("Execute api query", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests
		Billrun_Factory::log("Input: " . print_R($request, 1), Zend_Log::DEBUG);

		if (!isset($request['aid']) && !isset($request['sid'])) {
			$this->setError('Require to supply aid or sid', $request);
			return false;
		}
		
		$lines = $this->getResultLines($request);
		// Error occured.
		if($lines === false) {
			return false;
		}

		Billrun_Factory::log("query success", Zend_Log::INFO);
		$this->sendResults($request, $lines);
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
	
	/**
	 * Get query based on an array of values.
	 * @param array $billrun - Array of values to build the query from.
	 * @return array Result query.
	 */
	protected function getBillrunQuery($billrun) {
		return array('$in' => Billrun_Util::verify_array($this->getArrayParam($billrun), 'str'));
	}


}
