<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Action base class that uses compound parameters.
 *
 * @package  Action
 * @since    4
 */
abstract class Billrun_API_CompoundParamAction extends ApiAction{
	
	/**
	 * This function is called prior to the execute function.
	 */
	protected abstract function preExecute();
		
	/**
	 * Get the default filter for the query if the strip resulsts were empty.
	 */
	protected function getDefaultFilter();
	
	/**
	 * Return the cache params array built using the input request.
	 * @param type $request - Input request.
	 * @return array Array of cache parameters to use for API caching.
	 */
	protected function getCacheParams($request) {
		// If no query received, using empty array as default. 
		// TODO: Is this correct? or should an error be raised if no query received?
		$requestedQuery = $request->get('query', array());
		
		// Extract the compound parameters from the requested query.
		$queryToProccess = $this->getCompundParam($requestedQuery);
		$query = $this->processQuery($queryToProccess);
		$strip = $this->getCompundParam($request->get('strip', false));
		$filter = !empty($strip) ? $strip : $this->getDefaultFilter();
		
		$cacheParams = array(
			'fetchParams' => array(
				'query' => $query,
				'filter' => $filter,
				'strip' => $strip,
			),
			'stampParams' => array($requestedQuery, $filter, $strip),
		);
		
		return $cacheParams;
	}
	
	/**
	 * Handle the API cache logic.
	 * @param type $request - Received request.
	 * @return results of the API cache.
	 */
	protected function handleCache($request) {
		$this->setCacheLifeTime(Billrun_Utils_TimerUtils::daysToSeconds(1)); 
		return $this->cache($this->getCacheParams($request));
	}
	
	/**
	 * Execute the API's logic.
	 */
	public function execute() {
		$this->preExecute();
		$request = $this->getRequest();
		$results = $this->handleCache($request);
		
		$this->getController()->setOutput(array(array(
				'status' => 1,
				'desc' => 'success',
				'details' => $results,
				'input' => $request->getRequest(),
			)));
	}
	
	/**
	 * Fetch results from the related model using the fetch data params.
	 * @param array $params - Fetch data params.
	 * @return array of results from the model.
	 */
	protected abstract function fetchDataFromModel($params);
	
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
		
		$results = $this->fetchDataFromModel($params);
		if (isset($params['strip']) && !empty($params['strip'])) {
			$results = $this->stripResults($results, $params['strip']);
		}
		return $results;

	}

	/**
	 * Get an array of key => values to query in the process query function.
	 * @return array of keywords to query.
	 */
	protected function getValuesToQueryForProcessQuery() {
		return array('from' => '$lte', 'to' => '$gte');
	}
	
	/**
	 * Process the query and prepere it for usage by the Plans model
	 * @param type $query the query that was recevied from the http request after being
	 * proccessed in the getCompoundParams function.
	 * @return array containing the processed query.
	 */
	protected function processQuery($query) {
		$valuesToQuery = $this->getValuesToQueryForProcessQuery();
		
		// Go through the values to query.
		foreach ($valuesToQuery as $keyword => $condition) {
			// If the key word is not set, set it.
			if(!isset($query[$keyword])) {
				$query[$keyword][$condition] = new MongoDate();
			}
			// If the key word is set, check if it is a complex condition using the
			// function in mongo db.
			else {
				$query[$keyword] = Billrun_Db::intToMongoDate($query[$keyword]);
			}
		}
		
		return $query;
	}
	
	/**
	 * Sets a result in the correct field of the stripped array.
	 * @param array $stripped - Array to set the result in.
	 * @param string $result - String to set.
	 * @param srting $field - Field to set.
	 */
	protected function setStripResult($stripped, $result, $field) {
		if (is_array($result[$field])) {
			$stripped[$field] = array_merge(isset($stripped[$field]) ? $stripped[$field] : array(), $result[$field]);
		} else {
			$stripped[$field][] = $result[$field];
		}
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
			foreach ($results as $result) {
				// If the result is not set, continue.
				if (!isset($result[$field])) {
					continue;
				}
				
				// Set the result.
				$this->setStripResult($stripped, $result, $field);
			}
		}
		return $stripped;
	}

	/**
	 * process a compund http parameter (an array)
	 * @param array $param the parameter that was passed by the http.
	 * @return array Array of compound parameters.
	 */
	protected function getCompundParam($param) {
		// If received null params return an empty array.
		if(!isset($param) || $param === FALSE) {
			return $param;
		}

		// If the param is a string it is a JSON object, decode it.
		return is_string($param)		   ? 
			   (json_decode($param, true)) : 
			   ((array) $param);
	}

}
