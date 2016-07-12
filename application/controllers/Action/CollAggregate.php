<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Generic aggregation action class
 *
 * @package  Action
 * 
 * @since    5.0
 */
class AggregateAction extends ApiAction {

	protected $ISODatePattern = '/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z/';
	
	/**
	 * method to execute the query
	 * it's called automatically by the api main controller
	 */
	public function execute() {
		Billrun_Factory::log()->log("Executed aggregate api", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests
		Billrun_Factory::log()->log("Aggregate API Input: " . print_R($request, 1), Zend_Log::DEBUG);
		$config = Billrun_Factory::config()->getConfigValue('api.config.aggregate');

		if (($pipelines = $this->getArrayParam($request['pipelines'])) === FALSE) {
			$this->setError('Illegal pipelines: ' . $request['pipelines'], $request);
			return TRUE;
		}
		$pipelines = $this->convertToMongoIds($pipelines);
		$this->convertMongoDates($pipelines);
		if ($notPermittedPipelines = array_diff(array_map(function($pipeline) {
				return key($pipeline);
			}, $pipelines), $config['permitted_pipelines'])) {
			$this->setError('Illegal pipelines(s): ' . implode(', ', $notPermittedPipelines), $request);
			return true;
		}
		if (!$pipelines) {
			$this->setError('No query found', $request);
			return TRUE;
		}
		if (!empty($request['collection']) && in_array($request['collection'], Billrun_Util::getFieldVal($config['permitted_collections'], array()))) {
			$collection = $request['collection'];
			try {
				$entities = iterator_to_array(Billrun_Factory::db()->{$collection . 'Collection'}()->aggregate($pipelines));
				$entities = array_map(function($ele) {
					return $ele->getRawData();
				}, $entities);
			} catch (Exception $e) {
				$this->setError($e->getMessage(), $request);
				return TRUE;
			}

			Billrun_Factory::log()->log("query success", Zend_Log::INFO);
			$ret = array(
				array(
					'status' => 1,
					'desc' => 'success',
					'input' => $request,
					'details' => $entities,
				)
			);

			$this->getController()->setOutput($ret);
		} else {
			$this->setError('Illegal collection name: ' . $request['collection'], $request);
			return TRUE;
		}
	}

	/**
	 * method to retreive variable in dual way json or pure array
	 * 
	 * @param mixed $param the param to retreive
	 */
	protected function getArrayParam(&$param) {
		if (empty($param)) {
			return array();
		}
		if (is_string($param)) {
			$ret = json_decode($param, true);
			if (json_last_error()) {
				return FALSE;
			}
		} else {
			$ret = (array) $param;
		}
		return $ret;
	}

	protected function convertToMongoIds($query, $idSeen = FALSE) {
		if ($idSeen && is_string($query) && ctype_alnum($query)) {
			return new MongoId($query);
		}
		if (is_array($query)) {
			foreach ($query as $key => $value) {
				$query[$key] = $this->convertToMongoIds($value, $idSeen || $key === '_id');
			}
		}
		return $query;
	}
	
	protected function convertMongoDates(&$arr) {
		foreach ($arr as &$value) {
			if (is_array($value)) {
				$this->convertMongoDates($value);
			}
			if (preg_match($this->ISODatePattern, $value)) {
				$value = new MongoDate(strtotime($value));
			}
		}
	}

}
