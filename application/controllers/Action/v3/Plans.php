<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';
require_once APPLICATION_PATH . '/application/modules/Billapi/Models/Verification.php';
require_once APPLICATION_PATH . '/application/modules/Billapi/Models/Action.php';
require_once APPLICATION_PATH . '/application/modules/Billapi/Models/Action/Get.php';
require_once APPLICATION_PATH . '/application/modules/Billapi/Models/Action/Uniqueget.php';
require_once APPLICATION_PATH . '/application/modules/Billapi/Models/Entity.php';

/**
 * Plans action class of version 3
 *
 * @package  Action
 * 
 * @since    2.6
 */
class V3_PlansAction extends ApiAction {
	use Billrun_Traits_Api_UserPermissions;

	public function execute() {
//		$this->forward('billapi', 'uniqueget', 'index', ['collection' => 'plans', 'action' => 'uniqueget', 'translate' => true]);
//		return false;
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
		$model = new PlansModel(array('sort' => array('from' => 1)));
		$resource = $model->getData($params['query'], $params['filter']);
		if (is_resource($resource)) {
			$results = iterator_to_array($resource);
		} else if ($resource instanceof Mongodloid_Cursor) {
			$results = array();
			foreach ($resource as $item) {
				$rawItem = $item->getRawData();
				$rawItem['price'] = isset($rawItem['price']['0']['price']) ? $rawItem['price']['0']['price'] : 0;
				$results[] = Billrun_Utils_Mongo::convertRecordMongoDatetimeFields($rawItem);
			}
		}
		if (isset($params['strip']) && !empty($params['strip'])) {
			$results = $this->stripResults($results, $params['strip']);
		}
		$this->enrichPlansResponse($results);
		return $results;
	}
	
	protected function enrichPlansResponse(&$results) {
		Billrun_Factory::config()->addConfig(APPLICATION_PATH . '/conf/modules/billapi/services.ini');
		foreach ($results as &$plan) {
			$services = $this->getPlanServices($plan);
			$billableOptions = [];
			$nonBillableOptions = [];
			foreach ($services as $service) {
				$serviceName = $service['name'];
				$service['included'] = in_array($serviceName, Billrun_Util::getIn($plan, 'include.services', []));
				$service['price'] = isset($service['price']['0']['price']) ? $service['price']['0']['price'] : 0;
				$billable = Billrun_Util::getIn($service, 'billable', true);
				if ($billable) {
					$billableOptions[$serviceName] = $service;
				} else {
					$nonBillableOptions[$serviceName] = $service;
				}
			}
			$plan['options'] = $billableOptions;
			$plan['not_billable_options'] = $nonBillableOptions;
		}
	}
	
	protected function getPlanServices($plan) {
		$servicesNames = array_merge(Billrun_Util::getIn($plan, 'optional.services', []), Billrun_Util::getIn($plan, 'include.services', []));
		if (empty($servicesNames)) {
			return [];
		}
		$collection = 'services';
		$action = 'uniqueget';
		$query = [
			'name' => [ '$regex' => "^" . implode('$|^', $servicesNames) . "$" ],
		];
		$params = [
			'request' => [
				'collection' => $collection,
				'action' => $action,
				'query' => json_encode($query),
			],
			'settings' => $this->getActionConfig($collection, $action),
		];

		$action = new Models_Action_Uniqueget($params);
		return $action->execute();
	}
	
	protected function getActionConfig($collection, $action) {
		$configVar = 'billapi.' . $collection . '.' . $action;
		if (!isset($this->configs[$configVar])) {
			$this->configs[$configVar] = Billrun_Factory::config()->getConfigValue($configVar, []);
		}
		return $this->configs[$configVar];
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
