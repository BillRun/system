<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Billrun_Subscriber_External extends Billrun_Subscriber {
	
	static $queriesLoaded = false;
	
	static protected $type = 'external';
	
	protected static $queryBaseKeys = [ 'limit','time','id'];
	
	protected $remote;
	protected $remote_authentication;
		
	const API_DATETIME_REGEX='/^\d{4}-\d{2}-\d{2}(T|\s)\d{2}:\d{2}:\d{2}(\.\d{3}|)?(Z|[+-]\d\d\:?\d\d|)$/';
	public function __construct($options = array()) {
		parent::__construct($options);
		$this->setRemoteDetails(Billrun_Factory::config()->getConfigValue('subscribers.subscriber.external_url', ''));
		$defaultAuthentication = Billrun_Factory::config()->getConfigValue('subscribers.external_authentication', []);
		$this->remote_authentication = Billrun_Factory::config()->getConfigValue('subscribers.subscriber.external_authentication', $defaultAuthentication);
	}
	
	public function delete() {
		return true;
	}

	public function getCredits($billrun_key, $retEntity = false) {
		return array();
	}

	protected function getSubscriberDetails($queries, $globalLimit = FALSE, $globalDate = FALSE) {
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
		$request_type = Billrun_Http_Request::POST;
		Billrun_Factory::dispatcher()->trigger('beforeGetExternalSubscriberDetails', array(&$externalQuery, &$request_type, &$this));
		Billrun_Factory::log('Sending request to ' . $this->remote . ' with params : ' . json_encode($externalQuery), Zend_Log::DEBUG);		
		$params = [
			'authentication' => $this->remote_authentication,
			'timeout' => Billrun_Factory::config()->getConfigValue('external_api.timeout',600),
		];
		$request = new Billrun_Http_Request($this->remote, $params);
		$request->setHeaders(['Accept-encoding' => 'deflate', 'Content-Type'=>'application/json']);

		if($request_type == Billrun_Http_Request::POST) {
			$request->setParameterPost($externalQuery);
		} else {
		$request->setRawData(json_encode($externalQuery));
		}
		$results = $request->request($request_type)->getBody();
		Billrun_Factory::log('Receive response from ' . $this->remote . '. response: ' . $results, Zend_Log::DEBUG);
		$results = json_decode($results, true);
		Billrun_Factory::dispatcher()->trigger('afterGetExternalSubscriberDetailsResponse', array(&$results, $externalQuery));
		if (!$results) {
			Billrun_Factory::log()->log(get_class() . ': could not complete request to ' . $this->remote, Zend_Log::NOTICE);
			return false;
		}
		return array_reduce($results, function($acc, $currentSub) {
			Billrun_Utils_Mongo::convertQueryMongodloidDates($currentSub,static::API_DATETIME_REGEX);
			$acc[] = new Mongodloid_Entity($currentSub);
			return $acc;
		}, []);
	}

	public function isValid() {
		return true;
	}

	public function save() {
		return true;
	}
	
	protected function buildParams(&$query) {

		if (isset($query['EXTRAS'])) {
			unset($query['EXTRAS']);
		}
		$params = [];

		foreach ($query as $key => $value) {
			if (!in_array($key, static::$queryBaseKeys)) {
				$params[] = [
					'key' => $key,
					'operator' => 'equal',
					'value' => $value
					];
				unset($query[$key]);
			}
		}
		$query['params'] = $params;
		return $query;
	}
	
	public function getRemoteDetails() {
		return $this->remote;
	}
	
	public function setRemoteDetails($url) {
		$this->remote = $url;
	}
	
}

