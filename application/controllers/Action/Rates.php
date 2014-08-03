<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
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

	public function execute() {
		Billrun_Factory::log()->log("Execute rates api call", Zend_Log::INFO);
		$request = $this->getRequest();

		$query = $this->processQuery($request->get('query', array()));
		$strip = $this->getCompundParam($request->get('strip', false), false);
		$filter = !empty($strip) ? $strip : array('key', 'rates', 'provider');

		$model = new RatesModel(array('sort' => array('provider' => 1, 'from' => 1)));
		$results = $model->getData($query, $filter);
		if (!empty($strip)) {
			$results = $this->stripResults($results, $strip);
		}
		$this->getController()->setOutput(array(array(
				'status' => 1,
				'desc' => 'success',
				'details' => $results,
				'input' => $request->getRequest(),
			)));
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