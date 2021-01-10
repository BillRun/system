<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 BillRun Technologies Ltd. All rights reserved.
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
	use Billrun_Traits_Api_UserPermissions;
	
	/**
	 * method to execute the query
	 * it's called automatically by the api main controller
	 */
	public function execute() {
		$this->allowed();
		Billrun_Factory::log()->log("Execute find api", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests
		Billrun_Factory::log()->log("Find API Input: " . print_R($request, 1), Zend_Log::DEBUG);
		$config = Billrun_Factory::config()->getConfigValue('api.config.find');

		if (($query = $this->getArrayParam($request['query'])) === FALSE) {
			$this->setError('Illegal query: ' . $request['query'], $request);
			return TRUE;
		}
		$query = $this->convertToMongoIds($query);
		Billrun_Utils_Mongo::convertQueryMongodloidDates($query);
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

		if (empty($request['collection']) || !(in_array($request['collection'], Billrun_Util::getFieldVal($config['permitted_collections'], array())))) {
			$this->setError('Illegal collection name: ' . $request['collection'], $request);
			return TRUE;
		}	
		
		$collection = $request['collection'];
		try {
			$db = Billrun_Factory::db()->{$collection . 'Collection'}();
			$find = $db->find($query, $project)->sort($sort)->skip($page * $size)->limit($size + 1);
			
			// Get timeout
			// marked-out due to new mongodb driver (PHP7+) 
//			$timeout = Billrun_Factory::config()->getConfigValue("api.config.find.timeout", 60000);
//			$find->timeout($timeout);
			$entities = array_values(iterator_to_array($find));
            $next_page = count($entities) > $size;
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
                'next_page' => $next_page,
				'details' => array_slice($entities, 0, $size),
			)
		);

		$this->getController()->setOutput($ret);
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

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_READ;
	}

}
