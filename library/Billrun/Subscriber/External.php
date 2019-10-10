<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Billrun_Subscriber_External extends Billrun_Subscriber {
		
	public function delete() {
		
	}

	public function getCredits($billrun_key, $retEntity = false) {
		
	}

	public function getList($startTime, $endTime, $page, $size, $aid = null) {
		
	}

	public function getListFromFile($file_path, $time) {
		
	}

	public function getServices($billrun_key, $retEntity = false) {
		
	}

	public function getSubscribersByParams($params, $availableFields) {
		
	}

	public function isValid() {
		
	}

	public function save() {
		
	}
	
	public function getSubscriberDetails($query = []) {
		$remote = $this->getConfigValue('subscriber.fields.external', '');
		$res = Billrun_Util::sendRequest($remote, json_encode([$query]));
		$subs = [];
		if (!$res) {
			Billrun_Factory::log()->log(get_class() . ': could not complete request to' . $remote, Zend_Log::NOTICE);
			return false;
		}
		foreach ($res as $sub) {
			$subs[] = new Mongodloid_Entity($sub);
		}
		return $subs;
	}
	
}

