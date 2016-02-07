<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Rates action class
 *
 * @package  Action
 * 
 * @since    2.6
 */
class RatesAction extends ApiAction {

	protected $model;
	
	public function execute() {
		Billrun_Factory::log("Execute rates api call", Zend_Log::INFO);
		$request = $this->getRequest();
		$this->model = new RatesModel(array('sort' => array('provider' => 1, 'from' => 1)));


		$requestedQuery = $request->get('query', array());
		$query = $this->processQuery($requestedQuery);
		$strip = $this->getCompundParam($request->get('strip', false), false);
		$filter = !empty($strip) ? $strip : array('key', 'rates', 'provider');

		$cacheParams = array(
			'fetchParams' => array(
				'query' => $query,
				'filter' => $filter,
				'strip' => $strip,
			),
			'stampParams' => array($requestedQuery, $filter, $strip),
		);
		
		$this->setCacheLifeTime(86400); // 1 day TODO: Use time utils.
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
		$results = $this->model->getData($params['query'], $params['filter']);
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
			$matches = preg_grep('/rates.\w+.plans/', array_keys($retQuery));
			foreach($matches as $m) {
				$retQuery[$m] = $this->model->getPlan($retQuery[$m]);
			}
			
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
		return $retQuery;
	}

	/**
	 * Change numeric references to MongoDate object in a given filed in an array.
	 * @param MongoDate $arr 
	 * @param type $fieldName the filed in the array to alter
	 * @return the translated array
	 */
	// TODO: Moved this function to Billrun_Db.
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
		if(isset($param)) {
			$retParam =  $param ;
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

}