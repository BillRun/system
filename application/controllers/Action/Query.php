<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
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
	use Billrun_Traits_Api_UserPermissions;
	
	/**
	 * method to execute the query
	 * it's called automatically by the api main controller
	 */
	public function execute() {
		$this->allowed();
		$this->preExecute();
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests
		Billrun_Factory::log("Input: " . print_R($request, 1), Zend_Log::DEBUG);

		if (!$this->validateRequest($request)) {
			return false;
		}

		$lines = $this->getResultLines($request);
		// Error occured.
		if ($lines === false) {
			return false;
		}

		$this->postExecute();
		$this->sendResults($request, $lines);
	}

	/**
	 * Get the max list count.
	 * @return int The maximum number allowed for the query.
	 */
	protected function getMaxList() {
		return 1000;
	}

	/**
	 * Get the array of field names to check that their count does not pass the max.
	 * @return array Array of field names.
	 */
	protected function getFieldsToCheckForMaxList() {
		return array('aid', 'sid');
	}

	/**
	 * Get the count limit query part.
	 * @param array $request - Input request.
	 * @return boolean Array of queries according to received max size, false if invalid.
	 */
	protected function getMaxListQuery($request) {
		$returnQuery = array();

		$fieldsToCheck = $this->getFieldsToCheckForMaxList();

		foreach ($fieldsToCheck as $field) {
			if (!isset($request[$field])) {
				continue;
			}

			$queryLimitResults = $this->getQueryLimitForField($request, $field);

			// Error occured.
			if ($queryLimitResults === false) {
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
		if (count($verifiedArray) > $this->getMaxList()) {
			$this->setError('Maximum of ' . $paramName . ' is ' . $this->getMaxList(), $request);
			return false;
		}

		return array('$in' => $verifiedArray);
	}

	/**
	 * Sets additional values to the query.
	 * @param array $request Input array to set values by.
	 * @param array $query - Query to set values to.
	 */
	protected function setAdditionalValuesToQuery($request, &$query) {
		if (isset($request['billrun'])) {
			$query['billrun'] = $this->getBillrunQuery($request['billrun']);
		}

		if (isset($request['query'])) {
			$inputRequestQuery = $this->getArrayParam($request['query']);
			$query = array_merge($query, (array) $inputRequestQuery);
		}
		if (isset($request['from'])) {
			$query['urt'] = array(
				'$gte' => new MongoDate(strtotime($request['from'])),
			);
		}
		if (isset($request['to'])) {
			if (!isset($query['urt'])) {
				$query['urt'] = array(
					'$lt' => new MongoDate(strtotime($request['to'])),
				);
			} else {
				$query['urt']['$lte'] = new MongoDate(strtotime($request['to']));
			}
		}
	}

	/**
	 * Build the query for the api exectie based on the input request.
	 * @param array $request - Input request array.
	 * @return array The array to use for the query execute, false if error occured.
	 */
	protected function buildQuery($request) {
		$executeQuery = $this->getMaxListQuery($request);
		// Error occured.
		if (empty($executeQuery)) {
			// TODO: Return true on purpose? 
			return false;
		}

		$this->setAdditionalValuesToQuery($request, $executeQuery);

		return $executeQuery;
	}

	/**
	 * Get the lines data by the input request and query.
	 * @param array $request - Input request array.
	 * @param array $linesRequestQueries - Array of queries to be parsed to get the lines data.
	 * @return array lines to return for the action.
	 */
	protected function getLinesData($request, $linesRequestQueries) {
		$model = new LinesModel($linesRequestQueries['options']);
		$lines = null;
		$query = $linesRequestQueries['find'];
		if (isset($request['distinct'])) {
			$lines = $model->getDistinctField((string) $request['distinct'], $query);
		} else {
			$lines = $model->getData($query);
			foreach ($lines as &$line) {
				if (isset($line['source_ref'])) {
					$row = $line->get('source_ref', false)->getRawData();
					unset($row['tx'], $row['_id'], $row['notifications_sent']);
					$line['source_ref_value'] = $row;
				}
				$line = Billrun_Utils_Mongo::convertRecordMongoDatetimeFields($line->getRawData(), array('urt'));
			}
		}

		return $lines;
	}

	/**
	 * Get all the lines for the input query.
	 * @param array $request - Input request array.
	 * @return array of lines to return as result, false if error occurred.
	 */
	protected function getResultLines($request) {
		$executeQuery = $this->buildQuery($request);
		// Error occured.
		if ($executeQuery === false) {
			return false;
		}

		$queryOptions = $this->getQueryOptions($request);

		// Send the queries in an array.
		$linesRequestQueries = array('find' => $executeQuery, 'options' => $queryOptions);
		return $this->getLinesData($request, $linesRequestQueries);
	}

	/**
	 * Get the array of options to use for the query.
	 * @param array $request - Input request array.
	 * @return array Options array for the query.
	 */
	protected function getQueryOptions($request) {
		return array(
			'sort' => isset($request['sort']) ? json_decode($request['sort'], true) : array('urt' => -1),
			'page' => isset($request['page']) && $request['page'] > 0 ? (int) $request['page'] : 0,
			'size' => isset($request['size']) && $request['size'] > 0 ? (int) $request['size'] : $this->getMaxList(),
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
	 * The function to run before execute.
	 */
	protected function preExecute() {
		Billrun_Factory::log("Execute api query", Zend_Log::INFO);
	}

	/**
	 * The function to run after execute.
	 */
	protected function postExecute() {
		Billrun_Factory::log("query success", Zend_Log::INFO);
	}

	/**
	 * Get the array of fields that the request should have.
	 * @return array of field names.
	 */
	protected function getRequestFields() {
		return array('aid', 'sid');
	}

	/**
	 * Validate the input request.
	 * @param array $request - Input request to be validated.
	 * @return boolean true if valid.
	 */
	protected function validateRequest($request) {
		$requestFields = $this->getRequestFields();
		$ret = false;
		foreach ($requestFields as $field) {
			if (isset($request[$field])) {
				$ret = true;
			}
		}
		if ($ret === false) {
			$this->setError('Require to supply one of the following fields: ' . implode(', ', $requestFields), $request);
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

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_READ;
	}

}