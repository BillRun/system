<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Billrun_Subscriber_External extends Billrun_Subscriber {
	use Billrun_Subscriber_External_Cacheable;
	
	static $queriesLoaded = false;
	
	static protected $type = 'external';
	
	protected static $queryBaseKeys = [ 'limit','time','id'];
	
	protected $remote;
		
	public function __construct($options = array()) {
		parent::__construct($options);
		$this->remote = Billrun_Factory::config()->getConfigValue('subscribers.subscriber.external_url', '');
		$this->setEnableCaching(Billrun_Factory::config()->getConfigValue('subscribers.subscriber.enable_caching', false));
		$this->setCachingTTL(Billrun_Factory::config()->getConfigValue('subscribers.subscriber.caching_ttl', 300));
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

		$results = $this->loadCache($externalQuery, function($externalQuery) {
			Billrun_Factory::log('Sending request to ' . $this->remote . ' with params : ' . json_encode($externalQuery), Zend_Log::DEBUG);

			$results = Billrun_Util::sendRequest($this->remote,
														 json_encode($externalQuery),
														 Zend_Http_Client::POST,
														 ['Accept-encoding' => 'deflate','Content-Type'=>'application/json']);

			Billrun_Factory::log('Receive response from ' . $this->remote . '. response: ' . $results, Zend_Log::DEBUG);
			return json_decode($results, true);
		});

		if (!$results) {
			Billrun_Factory::log()->log(get_class() . ': could not complete request to ' . $this->remote, Zend_Log::NOTICE);
			return false;
		}
		return array_reduce($results, function($acc, $currentSub) {
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
}

