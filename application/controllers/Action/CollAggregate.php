<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 BillRun Technologies Ltd. All rights reserved.
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

	use Billrun_Traits_Api_UserPermissions;

	/**
	 * method to execute the query
	 * it's called automatically by the api main controller
	 */
	public function execute() {
		$this->allowed();

		try {
			Billrun_Factory::log()->log("Executed aggregate api", Zend_Log::INFO);
			$request = $this->getRequest()->getRequest(); // supports GET / POST requests
			Billrun_Factory::log()->log("Aggregate API Input: " . print_R($request, 1), Zend_Log::DEBUG);
			$config = Billrun_Factory::config()->getConfigValue('api.config.aggregate');

			if (($pipelines = $this->getArrayParam($request['pipelines'])) === FALSE || (!is_array($pipelines))) {
				$this->setError('Illegal pipelines: ' . $request['pipelines'], $request);
				return TRUE;
			}
			$pipelines = $this->convertToMongoIds($pipelines);
			Billrun_Utils_Mongo::convertQueryMongodloidDates($pipelines);
			if ($notPermittedPipelines = array_diff(array_map(function ($pipeline) {
						return key($pipeline);
					}, $pipelines), $config['permitted_pipelines'])) {
				$this->setError('Illegal pipelines(s): ' . implode(', ', $notPermittedPipelines), $request);
				return true;
			}
			if (!$pipelines) {
				$this->setError('No query found', $request);
				return TRUE;
			}
			if (empty($request['collection']) || !in_array($request['collection'], Billrun_Util::getFieldVal($config['permitted_collections'], array()))) {
				$this->setError('Illegal collection name: ' . $request['collection'], $request);
				return TRUE;
			}
			$lookups = array_filter($pipelines, function ($pipeline) {
				return key($pipeline) == '$lookup';
			});

			if (!empty($lookups)) {
				$lookupError = array_filter($lookups, function ($lookup) use ($config) {
					$collectionName = Billrun_Util::getFieldVal($lookup['$lookup']['from'], false);
					return (!in_array($collectionName, Billrun_Util::getFieldVal($config['permitted_collections'], array())));
				});

				if (!empty($lookupError)) {
					$this->setError('Illegal collection name in lookup pipeline', $request);
					return TRUE;
				}
			}

			$collection = $request['collection'];

			$cursor = Billrun_Factory::db()->{$collection . 'Collection'}()->aggregate($pipelines);

			// Set timeout of 1 minute
			// marked-out due to new mongodb driver (PHP7+)
//			$timeout = Billrun_Factory::config()->getConfigValue("api.config.aggregate.timeout", 60000);
//			$cursor->timeout($timeout);
			$entities = iterator_to_array($cursor);
			$entities = array_map(function ($ele) {
				return $ele->getRawData();
			}, $entities);

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
		} catch (Exception $e) {
			$this->setError($e->getMessage(), $request);
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
			return new Mongodloid_Id($query);
		}
		if (is_array($query)) {
			foreach ($query as $key => $value) {
				$query[$key] = $this->convertToMongoIds($value, $idSeen || $key === '_id');
			}
		}
		return $query;
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_READ;
	}

	protected function render($tpl, array $parameters = null) {
		$request = $this->getRequest()->getRequest();
		if (isset($request['response_type']) && $request['response_type'] === 'csv') {
			return $this->renderCsv($request, $parameters);
		}
		return parent::render($tpl, $parameters);
	}

	protected function renderCsv($request, array $parameters = null) {
		$filename = isset($request['file_name']) ? $request['file_name'] : 'aggregated';
		$headers = isset($request['headers']) ? $request['headers'] : array();
		$delimiter = isset($request['delimiter']) ? $request['delimiter'] : ',';
		$this->getController()->setOutputVar('headers', $headers);
		$this->getController()->setOutputVar('delimiter', $delimiter);
		$resp = $this->getResponse();
		$resp->setHeader("Cache-Control", "max-age=0");
		$resp->setHeader("Content-type", "application/csv");
		$resp->setHeader('Content-disposition', 'inline; filename="' . $filename . '.csv"');
		return $this->getView()->render('api/aggregatecsv.phtml', $parameters);
	}

}
