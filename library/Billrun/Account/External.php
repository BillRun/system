<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Billrun_Account_External extends Billrun_Account {

	protected $remote;
	
	public function __consrtuct($options = []) {
		parent::__construct($options);
		Yaf_Loader::getInstance(APPLICATION_PATH . '/application/modules/Billapi')->registerLocalNamespace("Models");
		$this->remote = $this->getConfigValue('subscriber.fields.external', '');
	}

	public function getList($page, $size, $time, $acc_id = null) {
		
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
	protected function getAccountDetails($query) {
		$res = Billrun_Util::sendRequest($this->remote, json_encode($query));
		if (!$res) {
			Billrun_Factory::log()->log(get_class() . ': could not complete request to' . $this->remote, Zend_Log::NOTICE);
			return false;
		}
		return new Mongodloid_Entity($res);
	}
	
	/** 
	 * Method to Save as 'Close And New' item
	 */
	public function closeAndNew($set_values, $remove_values = array()) {
		
	}

}

