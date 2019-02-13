<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Plans action class
 *
 * @package  Action
 * 
 * @since    2.6
 * @deprecated since version 5
 */
class PlansAction extends ApiAction {
	use Billrun_Traits_Api_UserPermissions;

	public function execute() {
		$this->forward('billapi', 'uniqueget', 'index', ['collection' => 'plans', 'action' => 'uniqueget', 'translate' => true]);
		return false;
		$this->allowed();
		Billrun_Factory::log("Execute plans api call", Zend_Log::INFO);
		$request = $this->getRequest();

		// If no query received, using empty array as default. 
		// TODO: Is this correct? or should an error be raised if no query received?
		$requestedQuery = $request->get('query', array());
		$query = $this->processQuery($requestedQuery);
		$strip = $this->getCompundParam($request->get('strip', false), false);
		$filter = !empty($strip) ? $strip : array('name');

		$cacheParams = array(
			'fetchParams' => array(
				'query' => $query,
				'filter' => $filter,
				'strip' => $strip,
			),
			'stampParams' => array($requestedQuery, $filter, $strip),
		);

		$this->setCacheLifeTime(Billrun_Utils_Time::daysToSeconds(1));
		$results = $this->cache($cacheParams);

		$this->getController()->setOutput(array(array(
				'status' => 1,
				'desc' => 'success',
				'details' => $results,
				'input' => $request->getRequest(),
		)));
	}

	/**
	 * basic fetch data method used by the cache
	 * 
	 * @param array $params parameters to fetch the data
	 * 
	 * @return boolean
	 */
	protected function fetchData($params) {
		if (is_null($params)) {
			$params = array();
		}
		if (!isset($params['query'])) {
			$params['query'] = array();
		}
		$params['query']['$or'] = array(
			array(
				'hiddenFromApi' => array(
					'$exists' => 0,
				)
			),
			array(
				'hiddenFromApi' => false
			),
			array(
				'hiddenFromApi' => 0
			)
		);
		$model = new PlansModel(array('sort' => array('from' => 1)));
		$resource = $model->getData($params['query'], $params['filter']);
		if (is_resource($resource)) {
			$results = iterator_to_array($resource);
		} else if ($resource instanceof Mongodloid_Cursor) {
			$results = array();
			foreach ($resource as $item) {
				$rawItem = $item->getRawData();
				$results[] = Billrun_Utils_Mongo::convertRecordMongoDatetimeFields($rawItem);
			}
		}
		if (isset($params['strip']) && !empty($params['strip'])) {
			$results = $this->stripResults($results, $params['strip']);
		}
		return $results;
	}

	/**
	 * Change the times of a mongo record
	 * @param record - Record to change the times of.
	 * @return The record with translated time.
	 * @deprecated There isw a function in the util module.
	 */
	protected function setTimeToReadable($record) {
		foreach (array('from', 'to') as $timeField) {
			$record[$timeField] = date(DATE_ISO8601, $record[$timeField]->sec);
		}

		return $record;
	}

	/**
	 * Process the query and prepere it for usage by the Plans model
	 * @param type $query the query that was recevied from the http request.
	 * @return array containing the processed query.
	 */
	protected function processQuery($query) {
		$retQuery = $this->getCompundParam($query, array());

		// TODO: This code appears multiple times in the project, 
		// should be moved to a more general class.
		if (!isset($retQuery['from'])) {
			$retQuery['from']['$lte'] = new MongoDate();
		} else {
			$retQuery['from'] = $this->intToMongoDate($retQuery['from']);
		}
		if (!isset($retQuery['to'])) {
			$retQuery['to']['$gte'] = new MongoDate();
		} else {
			$retQuery['to'] = $this->intToMongoDate($retQuery['to']);
		}

		return $retQuery;
	}

	/**
	 * Change numeric references to MongoDate object in a given filed in an array.
	 * @param MongoDate $arr 
	 * @param type $fieldName the filed in the array to alter
	 * @return the translated array
	 */
	protected function intToMongoDate($arr) {
		if (is_array($arr)) {
			foreach ($arr as $key => $value) {
				if (is_numeric($value)) {
					$arr[$key] = new MongoDate((int) $value);
				}
			}
		} else if (is_numeric($arr)) {
			$arr = new MongoDate((int) $arr);
		}
		return $arr;
	}

	/**
	 * 
	 * @param type $results
	 * @param type $strip
	 * @return type
	 * TODO: This function is found in the project multiple times, should be moved to a better location.
	 */
	protected function stripResults($results, $strip) {
		$stripped = array();
		foreach ($strip as $field) {
			foreach ($results as $rate) {
				if (isset($rate[$field])) {
					if (is_array($rate[$field])) {
						$stripped[$field] = array_merge(isset($stripped[$field]) ? $stripped[$field] : array(), $rate[$field]);
					} else {
						$stripped[$field][] = $rate[$field];
					}
				}
			}
		}
		return $stripped;
	}

	/**
	 * process a compund http parameter (an array)
	 * @param type $param the parameter that was passed by the http;
	 * @return type
	 */
	protected function getCompundParam($param, $retParam = array()) {
		if (isset($param)) {
			$retParam = $param;
			if ($param !== FALSE) {
				if (is_string($param)) {
					$retParam = json_decode($param, true);
				} else {
					$retParam = (array) $param;
				}
			}
		}
		return $retParam;
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_READ;
	}

}
