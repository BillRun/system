<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Rates action class of version 3
 *
 * @package  Action
 * 
 * @since    2.6
 */
class V3_ratesAction extends ApiAction {
	use Billrun_Traits_Api_UserPermissions;

	public function execute() {
		Billrun_Factory::log()->log("Execute rates api call", Zend_Log::INFO);
		$request = $this->getRequest();
		Billrun_Factory::log()->log("Query API Input: " . print_R($request->getRequest(), 1), Zend_Log::DEBUG);

		$requestedQuery = $request->get('query', array());
		$query = $this->processQuery($requestedQuery);
		$strip = $this->getCompundParam($request->get('strip', false), false);
		$filter = !empty($strip) ? $strip : array('_id', 'key', 'rates', 'provider', 'model', 'inventory_id', 'brand', 'ax_code', 'invoice_labels', 'zone_grouping','vti_name','type');
		$sort = @json_decode($request->get('sort', '{"from" : 1}'), JSON_OBJECT_AS_ARRAY);

		$cacheParams = array(
			'fetchParams' => array(
				'query' => $query,
				'filter' => $filter,
				'strip' => $strip,
				'sort' => $sort,
			),
			'stampParams' => array($requestedQuery, $filter, $strip),
		);

		$this->setCacheLifeTime(86400); // 1 day
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
		$model = new RatesModel(array('sort' => $params['sort']));
		if (is_null($params)) {
			$params = array();
		}
		if (!isset($params['query'])) {
			$params['query'] = array();
		}
		$params['query']['$or'] = array(
			array(
				'hidden_from_api' => array(
					'$exists' => 0,
				)
			),
			array(
				'hidden_from_api' => false
			),
			array(
				'hidden_from_api' => 0
			)
		);
		$results = $model->getData($params['query'], $params['filter']);
		if (isset($params['strip']) && !empty($params['strip'])) {
			$results = $this->stripResults($results, $params['strip']);
		}
		return $results;
	}

	/**
	 * Process the query and prepere it for usage by the Rates model
	 * @param type $query the query that was recevied from the http request.
	 * @return array containing the processed query.
	 */
	protected function processQuery($query) {
		$retQuery = array();

		if (isset($query)) {
			$retQuery = $this->getCompundParam($query, array());
			if (!empty($retQuery['_id']) && is_array($retQuery['_id'])) {
				$hexIds = $retQuery['_id'];
				unset($retQuery['_id']);
				foreach ($hexIds as $hexId) {
					if (MongoId::isValid($hexId)) {
						$retQuery['_id']['$in'][] = new MongoId($hexId);
					}
				}
			} else {
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
			}
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
	 */
	protected function stripResults($results, $strip) {
		$stripped = array();
		foreach ($strip as $field) {
			foreach ($results as $rate) {
				if (isset($rate[$field])) {
					if (is_array($rate[$field])) {
						$stripped[$field] = array_merge(isset($stripped[$field]) ? $stripped[$field] : array(), $rate[$field]);
					} elseif ($rate[$field] instanceof Mongodloid_Id) {
						$stripped[$field][] = strval($rate[$field]);
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
