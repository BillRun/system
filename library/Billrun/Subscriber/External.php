<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Billrun_Subscriber_External extends Billrun_Subscriber {
	
	static $queriesLoaded = false;
	
	static protected $type = 'external';
		
	public function __construct($options = array()) {
		parent::__construct($options);
		$this->remote = Billrun_Factory::config()->getConfigValue('subscriber.fields.external', '');
	}
	
	public function delete() {
		return true;
	}

	public function getCredits($billrun_key, $retEntity = false) {
		return array();
	}

	public function getList($startTime, $endTime, $page, $size, $aid = null) {
		
	}

	protected function getSubscribersDetails($params, $availableFields = []) {
		$res = Billrun_Util::sendRequest($this->remote, json_encode($params));
		$subscribers = [];
		if (!$res) {
			Billrun_Factory::log()->log(get_class() . ': could not complete request to' . $this->remote, Zend_Log::NOTICE);
			return false;
		}
		foreach ($res as $sub) {
			$subscribers[] = new Mongodloid_Entity($sub);
		}
		return $subscribers;
	}
	
	protected function getSubscriberDetails($queries) {
		$subs = [];
		foreach ($queries as $query) {

			if (isset($query['id'])) {
				$id = $query['id'];
				unset($query['id']);
			}

			if (isset($query['EXTRAS'])) {
				unset($query['EXTRAS']);
			}
			
			$result = Billrun_Util::sendRequest($this->remote, json_encode($query));
			if (!$result) {
				Billrun_Factory::log()->log(get_class() . ': could not complete request to' . $this->remote, Zend_Log::NOTICE);
				return false;
			}
		  	foreach ($result as $sub) {
				$subscriber = new Mongodloid_Entity($sub);
				if (isset($id)) {
					$subscriber->set('id', $id);
				}
				$subs[] = $subscriber;
			}
		}
		return $subs;
	}

	public function isValid() {
		return true;
	}

	public function save() {
		return true;
	}
	
}

