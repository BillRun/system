<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Billrun_Account_External extends Billrun_Account {
	
	protected static $type = 'external';
	
	protected static $queryBaseKeys = ['id', 'time', 'limit'];
	
	protected $remote;
    protected $remote_billable_url;

	const API_DATETIME_REGEX='/^\d{4}-\d{2}-\d{2}[\sT]\d{2}:\d{2}:\d{2}$/';

	public function __construct($options = []) {
		parent::__construct($options);
		$this->remote = Billrun_Factory::config()->getConfigValue(	'subscribers.account.external_url',
																	Billrun_Util::getFieldVal($options['external_url'],	''));
		$this->remote_billable_url = Billrun_Factory::config()->getConfigValue('subscribers.billable.url', '');
	}
	

	public function getBillable(\Billrun_DataTypes_MongoCycleTime $cycle, $page = 0 , $size = 100, $aids = []) {
			// Prepare request
			$requestParams = [
				'start_date' => date('Y-m-d',$cycle->start()->sec),
				'end_date' => date('Y-m-d',$cycle->end()->sec),
				'page' => $page,
				'size' => $size
			];

			if(!empty($aids)) {
				$requestParams['aids'] = implode(',',$aids);
			}
			Billrun_Factory::log('Sending request to ' . $this->remote_billable_url . ' with params : ' . json_encode($requestParams), Zend_Log::DEBUG);
			//Actually  do the request
			$results = Billrun_Util::sendRequest($this->remote_billable_url,$requestParams);
			
			Billrun_Factory::log('Receive response from ' . $this->remote_billable_url . '. response: ' . $results, Zend_Log::DEBUG);
			
			$results = json_decode($results, true);		
			//Check for errors
			if(empty($results)) {
				Billrun_Factory::log('Failed to retrive valid results for billable, remote returned no data.',Zend_Log::WARN);
				return [];
			}
			if( empty($results['status']) || !isset($results['data']) ) {
				Billrun_Factory::log("Remote server return an error (status : {$results['status']}) on request : ".json_encode($requestParams), Zend_Log::ALERT);
				return [];
			}
			
			// Preform translation if needed and return results
			$fieldMapping = ['firstname' => 'first_name', 'lastname' => 'last_name'];
			foreach($results['data'] as &$rev) {
				Billrun_Utils_Mongo::convertQueryMongoDates($rev, static::API_DATETIME_REGEX);
				foreach($fieldMapping as $srcField => $dstField) {
					if(isset($rev[$srcField])) {
						$rev[$dstField] = $rev[$srcField];
					}
				}

			}
			return $results;
	}


	/**
	 * Overrides parent abstract method
	 */
	protected function getAccountsDetails($query, $globalLimit = FALSE, $globalDate = FALSE) {
		$requestData = ['query' => $query];
		if($globalLimit) {
			$requestData['limit'] = $globalLimit;
		}
		if($globalDate) {
			$requestData['date'] = $globalDate;
		}
		Billrun_Factory::log('Sending request to ' . $this->remote . ' with params : ' . json_encode($requestData), Zend_Log::DEBUG);
		$res = Billrun_Util::sendRequest($this->remote,
													 json_encode($requestData),
													 Zend_Http_Client::POST,
													 ['Accept-encoding' => 'deflate','Content-Type'=>'application/json']);
		Billrun_Factory::log('Receive response from ' . $this->remote . '. response: ' . $res, Zend_Log::DEBUG);
		$res = json_decode($res);
		
		$accounts = [];
		if (!$res) {
			Billrun_Factory::log()->log(get_class() . ': could not complete request to ' . $this->remote, Zend_Log::NOTICE);
			return false;
		}
		
		foreach ($res as $account) {
			Billrun_Utils_Mongo::convertQueryMongoDates($account, static::API_DATETIME_REGEX);
			$accounts[] = new Mongodloid_Entity($account);
		}
		return $accounts;
	}
	
	/**
	 * Overrides parent abstract method
	 */
	protected function getAccountDetails($queries, $globalLimit = FALSE, $globalDate = FALSE) {
		$externalQuery = [];
		foreach ($queries as &$query) {
			$query = $this->buildParams($query);
			if (!isset($query['id'])) {
				$query['id'] = Billrun_Util::generateArrayStamp($query);
			}
			$externalQuery['query'][] = $query;
		}
		if($globalLimit) {
			$externalQuery['limit'] = $globalLimit;
		}
		if($globalDate) {
			$externalQuery['date'] = $globalDate;
		}
		Billrun_Factory::log('Sending request to ' . $this->remote . ' with params : ' . json_encode($externalQuery), Zend_Log::DEBUG);		
		$results = Billrun_Util::sendRequest($this->remote,
														 json_encode($externalQuery),
														 Zend_Http_Client::POST,
														 ['Accept-encoding' => 'deflate','Content-Type'=>'application/json']);		
		
		Billrun_Factory::log('Receive response from ' . $this->remote . '. response: ' . $results ,Zend_Log::DEBUG);
		$results = json_decode($results, true);
		if (!$results) {
			Billrun_Factory::log()->log(get_class() . ': could not complete request to ' . $this->remote, Zend_Log::NOTICE);
			return false;
		}
		return array_reduce($results, function($acc, $currentAcc) {
			Billrun_Utils_Mongo::convertQueryMongoDates($currentAcc, static::API_DATETIME_REGEX);
			$acc[] = new Mongodloid_Entity($currentAcc);
			return $acc;
		}, []);
	}


	
	/** 
	 * Method to Save as 'Close And New' item
	 */
	public function closeAndNew($set_values, $remove_values = array()) {
		
	}

	protected function buildParams(&$query) {

		if (isset($query['EXTRAS'])) {
			unset($query['EXTRAS']);
		}
		$params = [];
		foreach ($query as $key => $value) {
			if (!in_array($key, static::$queryBaseKeys)) {
				if (is_array($value)) {
					foreach ($value as $currKey => $currVal) {
						$params[] = [
						'key' => $key,
						'operator' => preg_replace('/^\$/', '',$currKey), // match the docs
						'value' => $currVal
						];
					}
				} else {
					$params[] = [
						'key' => $key,
						'operator' => 'equal',
						'value' => $value
						];
				}
				unset($query[$key]);
			}
		}
		$query['params'] = $params;
		return $query;
	}

}

