<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Query action class
 *
 * @package  Action
 *
 * @since    2.6
 */
class ReportAction extends ApiAction {

		/**
	 * method to execute the query
	 * it's called automatically by the api main controller
	 */
	public function execute() {
		Billrun_Factory::log()->log("Execute api usage report query", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests
		$input = json_decode($request['query'],JSON_OBJECT_AS_ARRAY);
		$met  = $this->getRequest()->getMethod();
		if(empty($input)) {
			$this->setError("Please provide a valid json query",-1,'E_INVALID_INPUT');
			return;
		}

		if( !$this->validateInput($input) ) {
			return;
		}

 		$actionType = Billrun_Util::regexFirstValue("/^\/api\/report\/(\w+)/",$this->getRequest()->getRequestUri());

		$cacheParams = array(
			'fetchParams' => array(
				'sub_action' => $actionType,
				'input' => $input,
			),
		);

		$this->setCacheLifeTime(7200); // 2 hours
		try {
			$results = $this->cache($cacheParams);
			if($results === FALSE) {
				return;
			}
		} catch(\Exception $e) {
			$this->setError('Internal Error : '.$e->getMessage(), $e->getCode(), 'E_INTERNAL_ERROR');
		}

		Billrun_Factory::log()->log("balances usage report success", Zend_Log::INFO);
		$ret = array(
			array(
				'result' => 1,
				'input' => $request,
				'data' => [
					'rows' => $results,
					'limit' => @$input['limit'],
					'skip' => @$input['skip'],
					'totalRows' => count($results),
				]
			)
		);
		$this->getController()->setOutput($ret);
	}


	protected function fetchData(array $params)
	{
		switch($params['sub_action']) {
			case "usage" : return $this->fetchUsage($params);
				break;
			case "ipmapping" : return $this->fetchIpMapping($params);
				break;
			default: $this->setError("Invalid action reuqested {$params['sub_action']}.", -3, 'E_INVALID_ACTION');
		}

		return FALSE;
	}


	/**
	 * basic fetch data method used by the cache
	 *
	 * @param array $params parameters to fetch the data
	 *
	 * @return boolean
	 */
	protected function fetchUsage($params) {
		$input = $params['input'];
		$query = $this->getMongoQueryFromInput($input);
		$cursor = Billrun_Factory::db()->linesCollection()->query($query)->cursor();

		if(!empty($input['sortColumn'])) {
			$cursor->sort([ $input['sortColumn'] => (empty($input['sortDir']) ? intval($input['sortDir']) : 1)]);
		}
		if(!empty($input['skip'])) {
			$cursor->skip(intval($input['skip']));
		}
		if(!empty($input['limit'])) {
			$cursor->skip(intval($input['limit']));
		}

		$retRows =[];
		foreach($cursor as $row) {
			$retRows[]= $row->getRawData();
		}
		return $retRows;
	}

	/**
	 * basic fetch data method used by the cache
	 *
	 * @param array $params parameters to fetch the data
	 *
	 * @return boolean
	 */
	protected function fetchIpmapping($params) {
		$input = $params['input'];
		$query = $this->getMongoQueryFromInput($input);
		$cursor = Billrun_Factory::db()->linesCollection()->query($query)->cursor();

		if(!empty($input['sortColumn'])) {
			$cursor->sort([ $input['sortColumn'] => (empty($input['sortDir']) ? intval($input['sortDir']) : 1)]);
		}
		if(!empty($input['skip'])) {
			$cursor->skip(intval($input['skip']));
		}
		if(!empty($input['limit'])) {
			$cursor->skip(intval($input['limit']));
		}

		$retRows =[];
		foreach($cursor as $row) {
			$retRows[]= $row->getRawData();
		}
		return $retRows;
	}

	static protected $fieldMapping = [
		'duration' => ['$in'=>['duration']],
		'usageType' => ['$in'=>['usaget']],
		'sourcePhoneNumber' => ['$in'=>['calling_number']],
		'targetPhoneNumber' => ['$in'=>['called_number']],
		'sourceImei' => ['$in'=>[ 'imei' ]],
		'sourceEndpointType' =>  ['$in'=>['']],
		'targetEndpointType' =>  ['$in'=>['']],
		'sourceImsi' =>  ['$in'=>['imsi']],
		'targetImsi' =>  ['$in'=>['called_imsi']],
		'serviceType' =>  ['$in'=>['basic_service_type']],
		'startCellId' =>  ['$in'=>['called_subs_first_ci','calling_subs_first_ci']],
// 		'startSector' =>  ['$in'=>['']],
//		'startCgi' =>  ['$in'=>['']],
		'startLac' =>  ['$in'=>['called_subs_first_lac', 'callling_subs_first_lac']],
		'startSiteName' =>  ['$in'=>['apnni']],
		'startSiteAddress' =>  ['$in'=>['sgsn_address']],
		'endCellId' =>  ['$in'=>['called_subs_last_ci','calling_subs_last_ci']],
//		'endSector' =>  ['$in'=>['']],
//		'endCgi' =>  ['$in'=>['']],
		'endLac' =>  ['$in'=>['called_subs_last_lac', 'callling_subs_last_lac']],
//		'endSiteName' =>  ['$in'=>['']],
//		'endSiteAddress' =>  ['$in'=>['']],
		'sourceIp4' =>  ['$in'=>['ipmapping.external_ip']],
//		'sourceIp6' =>  ['$in'=>['ipmapping.ipv6']],
		'startPort' =>  ['$gte'=> 'ipmapping.start_port'],
		'endPort' =>  ['$lte'=> 'ipmapping.end_port'],
//		'counterpartCarrier' =>  ['$in'=>['']],
		'countryOfOrigin' =>  ['$in'=> ['alpha3']],
	];

	protected function getMongoQueryFromInput($input) {
		$startDate =  preg_match("/^\d+$/",$input['startDate']) ? $input['startDate'] : strtotime($input['startDate']);
		$endDate =  preg_match("/^\d+$/",$input['startDate']) ? $input['endDate'] : strtotime($input['endDate']);
		$query = [ 'urt'=> ['$gte' => new MongoDate($startDate),
							'$lt' => new MongoDate($endDate) ]];

		foreach(static::$fieldMapping as $inputField => $toMap) {
			if(!empty($input[$inputField])) {
				$localOr = ['$or' => []];
				foreach($toMap as $equalOp => $internalFields) {
					if(is_array($internalFields)) {
						foreach($internalFields as $internalField) {
							$localOr['$or'][]  = [ $internalField => [$equalOp => is_array($input[$inputField]) ? $input[$inputField] : ["".$input[$inputField]] ] ];
						}
					} else {
						$localOr['$or'][]  = [ $internalFields => [$equalOp => "".$input[$inputField]] ];
					}
				}
				$query['$and'][] = $localOr;
			}
		}

		if(!empty($input['searchColumns'])) {
			$query['$or'] = [];
			$input['searchColumns'] =  is_array($input['searchColumns']) ? $input['searchColumns'] : [$input['searchColumns']];
			$input['searchValue'] =  is_array($input['searchValue']) ? $input['searchValue'] : [$input['searchValue']];
			foreach($input['searchColumns'] as  $field) {
				foreach($input['searchValue'] as $value) {
					$query['$or'][] = [$field => $value];
				}

			}
		}

		return $query;
	}

	protected function validateInput($input) {
		$requiredFields = ['startDate','endDate'];
		if(!empty($missingFields = array_diff($requiredFields,array_keys($input)))) {
				$this->setError("Missing fields in the input :".implode(",",$missingFields),-2,'E_INVALID_INPUT');
				return FALSE;
		}
		return TRUE;
	}


	function setError($error_message, $code = 0, $key = "E_UNKOWNERROR") {
		Billrun_Factory::log()->log("Sending Error : {$error_message}", Zend_Log::NOTICE);
		$output = array(
			'code' => $code,
			'key' => $key,
			'desc' => $error_message,
		);
		$this->getController()->setOutput(array($output));
		return;
	}

}
