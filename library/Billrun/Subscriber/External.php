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
		
		if (!self::$queriesLoaded) {
			self::$queriesLoaded = true;
			Billrun_Subscriber_Query_Manager::register(new Billrun_Subscriber_Query_Types_Imsi());
			Billrun_Subscriber_Query_Manager::register(new Billrun_Subscriber_Query_Types_Msisdn());
			Billrun_Subscriber_Query_Manager::register(new Billrun_Subscriber_Query_Types_Sid());
			Billrun_Subscriber_Query_Manager::register(new Billrun_Subscriber_Query_Types_Custom());
		}
		
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
	
	protected function getSubscriberDetails($query) {
		$res = Billrun_Util::sendRequest($this->remote, json_encode($query));
		if (!$res) {
			Billrun_Factory::log()->log(get_class() . ': could not complete request to' . $this->remote, Zend_Log::NOTICE);
			return false;
		}
		return new Mongodloid_Entity($res);
	}

	public function isValid() {
		return true;
	}

	public function save() {
		return true;
	}
	
}

