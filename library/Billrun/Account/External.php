<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Billrun_Account_External extends Billrun_Account {
	
	protected static $type = 'external';
	
	protected static $queryBaseKeys = ['id', 'time', 'limit'];
		
	public function __consrtuct($options = []) {
		parent::__construct($options);
		Yaf_Loader::getInstance(APPLICATION_PATH . '/application/modules/Billapi')->registerLocalNamespace("Models");
	}
	
	/**
	 * Overrides parent abstract method
	 */
	protected function getAccountsDetails($query) {
		$res = Billrun_Util::sendRequest($this->remote, json_encode($query));
		$accounts = [];
		if (!$res) {
			Billrun_Factory::log()->log(get_class() . ': could not complete request to' . $this->remote, Zend_Log::NOTICE);
			return false;
		}
		foreach ($res as $account) {
			$accounts[] = new Mongodloid_Entity($account);
		}
		return $accounts;
	}
	
	/**
	 * Overrides parent abstract method
	 */
	protected function getAccountDetails($queries) {
		$externalQuery = [];
		foreach ($queries as &$query) {
			$query = $this->buildParams($query);
			$externalQuery[] = $query;
		}
		$results = json_decode(Billrun_Util::sendRequest('http://billrun/api/test', json_encode($externalQuery)), true);
		if (!$results) {
			Billrun_Factory::log()->log(get_class() . ': could not complete request to' . $this->remote, Zend_Log::NOTICE);
			return false;
		}
		return array_reduce($results, function($acc, $currentSub) {
			$acc[] = new Mongodloid_Entity($currentSub);
			return $acc;
		}, []);
	}
	
	/** 
	 * Method to Save as 'Close And New' item
	 */
	public function closeAndNew($set_values, $remove_values = array()) {
		
	}
	
//	protected function buildQuery($params) {
//		$query = array('type' => 'account');
//		$queryExcludeParams = array('time', 'type', 'to', 'from');
//		
//		if (isset($params['time'])) {
//			$query['date'] = new MongoDate(strtotime($params['time']));
//		} else {
//			$query['date'] = new MongoDate();
//		}
//
//		foreach ($params as $key => $value) {
//			if (in_array($key, $queryExcludeParams)) {
//				continue;
//			}
//			$query[$key] = $value;
//		}
//
//		return $query;
//	}
//	
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
						'operator' => $currKey,
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
	
	protected function getTimeQuery($time) {
		return array('time' => $time);
	}

}

