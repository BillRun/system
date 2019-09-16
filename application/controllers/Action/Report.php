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

	protected $fieldMapping = [
		'duration' => ['$in'=>['duration']],
		'usageType' => ['$regex'=>'usaget'],
		'sourcePhoneNumber' => ['$in'=>['calling_number']],
		'targetPhoneNumber' => ['$in'=>['called_number']],
		'sourceImei' => ['$in'=>[ 'imei' ]],
//		'sourceEndpointType' =>  ['$in'=>['']],
//		'targetEndpointType' =>  ['$in'=>['']],
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
		'sourceIp4' =>  ['$in'=>['external_ip']],
		'externalIp4' =>  ['$in'=>['external_ip']],
		'internalIp4' =>  ['$in'=>['internal_ip']],
//		'sourceIp6' =>  ['$in'=>['ipmapping.ipv6']],
		'startPort' =>  ['$gte'=> 'start_port'],
		'endPort' =>  ['$lte'=> 'end_port'],
		'counterpartCarrier' =>  ['$in'=>['outgoiging_circuit_group','incoming_circuit_group']],
		'countryOfOrigin' =>  ['$in'=> ['alpha3']],
	];


	protected $fieldReverseMapping = [
		'duration' => 'duration',
		'usagev' => 'duration',
		'usaget' => 'usageType',
		'calling_number' => 'sourcePhoneNumber',
		'called_number' => 'targetPhoneNumber',
		'imei'=>'sourceImei',
// 		'' => 'sourceEndpointType',
// 		'' => 'targetEndpointType',
		'imsi'=>'sourceImsi',
		'called_imsi'=>'targetImsi',
		'basic_service_type' => 'serviceType',
		'called_subs_first_ci'=> 'startCellId',
		'calling_subs_first_ci' => 'startCellId',
// 		''=>'startSector',//not our responsibilty
//		''=>'startCgi', //not our responsibilty
		'called_subs_first_lac'=>'startLac',
		'callling_subs_first_lac'=>'startLac',
//		'apnni' => 'startSiteName', //not our responsibilty
//		'sgsn_address'=>'startSiteAddress', //not our responsibilty
		'called_subs_last_ci' => 'endCellId',
		'calling_subs_last_ci' => 'endCellId',
//		'' => 'endSector',//not our responsibilty
//		'' => 'endCgi',//not our responsibilty
		'called_subs_last_lac'  => 'endLac',
		'callling_subs_last_lac' => 'endLac',
//		'' => 'endSiteName',//not our responsibilty
//		'' => 'endSiteAddress',//not our responsibilty
		'ipmapping.external_ip' => 'sourceIp4',
		'ipmapping.external_ip' => 'externalIp4',
		'ipmapping.internal_ip' => 'internalIp4',
//		'ipmapping.ipv6' => 'sourceIp6', // no ip6 in golan
		'ipmapping.start_port' => 'startPort',
		'ipmapping.end_port' => 'endPort',
		'incoming_circuit_group' => 'counterpartCarrier',
		'outgoiging_circuit_group' => 'counterpartCarrier',
		'serving_network' => 'countryOfOrigin',
	];

	protected $ipmappingInputFields = [
		'sourceIp4' => 1,
		'externalIp4' =>  1,
		'internalIp4' =>  1,
		'startPort' =>  1,
		'endPort' =>  1,
	];

	protected $ipMapFieldsToReturn = ['datetime'=>1,'internal_ip'=>1,'external_ip'=>1,'start_port'=>1,'end_port'=>1,'change_type'=>1,'network'=>1 ];

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
		session_write_close();
 		$actionType = Billrun_Util::regexFirstValue("/^\/api\/report\/(\w+)/",$this->getRequest()->getRequestUri());

 		$this->fieldMapping = Billrun_Factory::config()->getConfigValue('police_report.field_mapping',$this->fieldMapping);
 		$this->fieldReverseMapping = Billrun_Factory::config()->getConfigValue('police_report.field_reverse_mapping',$this->fieldReverseMapping);

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
			return;
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


	protected function fetchData($params)
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
		$cursor = Billrun_Factory::db()->linesCollection()->query($query)->cursor()->setRawReturn(true);

		if(!empty($input['sortColumn'])) {
			$cursor->sort([ $input['sortColumn'] => (empty($input['sortDir']) ? intval($input['sortDir']) : 1)]);
		}
		if(!empty($input['skip'])) {
			$cursor->skip(intval($input['skip']));
		}
		if(!empty($input['limit'])) {
			$cursor->skip(intval($input['limit']));
		}

		return $this->translateResults($cursor);
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

		$ipmappingInput = array_intersect_key($input,$this->ipmappingInputFields);
		$linesInput = array_diff_key($input,$ipmappingInput);
		$ipmappingInput['startDate'] = $input['startDate'];
		$ipmappingInput['endDate'] = $input['endDate'];
		Billrun_Factory::log('Quering ipmapping...',Zend_log::DEBUG);


		$ipmQuery = $this->getMongoQueryFromInput($ipmappingInput);
		$ipmCursor = Billrun_Factory::db()->ipmappingCollection()->query($ipmQuery)->cursor()->sort(['urt'=>-1])->setRawReturn(true);
		$upto = PHP_INT_MAX;
		$ipmappings = [];
		Billrun_Factory::log('loading ipmapping...',Zend_log::DEBUG);
		foreach($ipmCursor as $mapping) {
			$map = $mapping;
			$map['end_map_date'] = $upto;
			$upto = $mapping['urt']->sec;
			$linesQuery = $this->getMongoQueryFromInput($linesInput);
			$linesQuery['served_pdp_address'] = $mapping['internal_ip'];
			if(!empty($linesQuery['urt']['$gte'])) {
				$linesQuery['urt']['$gte'] = $mapping['urt'];
			} else {
				$linesQuery['urt'] = ['$gte' => $mapping['urt']];
			}
			$queries['$or'][] = $linesQuery;
			$ipmappings[] = $map;
		}
		Billrun_Factory::log('quering lines...',Zend_log::DEBUG);
		$cursor = Billrun_Factory::db()->linesCollection()->query($queries)->cursor()->setRawReturn(true);

		if(!empty($input['sortColumn'])) {
			$cursor->sort([ $input['sortColumn'] => (empty($input['sortDir']) ? intval($input['sortDir']) : 1)]);
		}
		if(!empty($input['skip'])) {
			$cursor->skip(intval($input['skip']));
		}
		if(!empty($input['limit'])) {
			$cursor->skip(intval($input['limit']));
		}


		return $this->translateResults($this->associateMapping($cursor, $ipmappings));
	}

	protected function getMongoQueryFromInput($input) {
		$startDate =  preg_match("/^\d+$/",$input['startDate']) ? $input['startDate'] : strtotime($input['startDate']);
		$endDate =  preg_match("/^\d+$/",$input['endDate']) ? $input['endDate'] : strtotime($input['endDate']);
		$query = [ 'urt'=> ['$gte' => new MongoDate($startDate),
							'$lt' => new MongoDate($endDate) ]];

		foreach($this->fieldMapping as $inputField => $toMap) {
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

	protected function translateResults($results) {
		$retRows =[];
		foreach($results as $row) {
			$retRow = $row instanceof Mongodloid_Entity ? $row->getRawData() : $row;
			foreach($this->fieldReverseMapping as  $srcField => $dstField ) {
				if(isset($retRow[$srcField])) {
					$retRow[$dstField] = $retRow[$srcField];
				}
			}
			$retRows[] = $retRow;
		}

		return $retRows;
	}

	protected function associateMapping( $results, $ipmapping) {
		$retRows =[];
		Billrun_Factory::log('Associating ipmapping...',Zend_log::DEBUG);
		foreach($ipmapping as $mapping) {
			foreach($results as $cdr) {
				if($cdr['urt'] > $mapping['urt'] && $mapping['end_map_date'] > $cdr['urt']->sec) {
					$cdr['ipmapping'] = array_intersect_key($mapping, $this->ipMapFieldsToReturn);
					$retRows[] = $cdr;
				}
			}
		}

		return $retRows;
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
