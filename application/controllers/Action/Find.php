<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Find action class
 *
 * @package  Action
 * 
 * @since    2.6
 */
class FindAction extends ApiAction {

	/**
	 * method to execute the query
	 * it's called automatically by the api main controller
	 */
	public function execute() {
		Billrun_Factory::log()->log("Execute find api", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests
		Billrun_Factory::log()->log("Find API Input: " . print_R($request, 1), Zend_Log::DEBUG);
		$config = Billrun_Factory::config()->getConfigValue('api.config.find');

		if (($query = $this->getArrayParam($request['query'])) === FALSE) {
			$this->setError('Illegal query: ' . $request['query'], $request);
			return TRUE;
		}
		$query = $this->convertToMongoIds($query);
		if (($project = $this->getArrayParam($request['project'])) === FALSE) {
			$this->setError('Illegal project: ' . $request['project'], $request);
			return TRUE;
		}
		if (($sort = $this->getArrayParam($request['sort'])) === FALSE) {
			$this->setError('Illegal sort: ' . $request['sort'], $request);
			return TRUE;
		}

		if (isset($request['page']) && is_numeric($request['page'])) {
			$page = $request['page'];
		} else {
			$page = 0;
		}

		if (isset($request['size']) && is_numeric($request['size']) && $request['size'] > 0) {
			$size = intval($request['size']);
		} else {
			$size = intval($config['default_page_size']);
		}
		if ($size > $config['maximum_page_size']) {
			$size = intval($config['maximum_page_size']);
		}

		if (!empty($request['collection']) && in_array($request['collection'], Billrun_Util::getFieldVal($config['permitted_collections'], array()))) {
			$collection = $request['collection'];
			try {
				$entities = iterator_to_array(Billrun_Factory::db()->{$collection . 'Collection'}()->find($query, $project)->sort($sort)->skip($page * $size)->limit($size));
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

}
