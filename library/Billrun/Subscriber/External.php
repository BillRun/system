<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Billrun_Subscriber_External extends Billrun_Subscriber {
		
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

	public function getListFromFile($file_path, $time) {
		
	}

	public function getSubscribersByParams($params, $availableFields) {
		$res = Billrun_Util::sendRequest($this->remote, json_encode($params));
		$subscribers = [];
		if (!$res) {
			Billrun_Factory::log()->log(get_class() . ': could not complete request to' . $remote, Zend_Log::NOTICE);
			return false;
		}
		foreach ($res as $sub) {
			$subscribers[] = new Mongodloid_Entity($sub);
		}
		return $subscribers;
	}

	public function isValid() {
		return true;
	}

	public function save() {
		return true;
	}
	
	public function getSubscriberDetails($query = []) {
		$res = Billrun_Util::sendRequest($this->remote, json_encode([$query]));
		$subs = [];
		if (!$res) {
			Billrun_Factory::log()->log(get_class() . ': could not complete request to' . $remote, Zend_Log::NOTICE);
			return false;
		}
		return new Mongodloid_Entity($res);
	}
	
}

