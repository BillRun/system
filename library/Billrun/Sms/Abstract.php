<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing sending Sms alerts
 *
 * @package  Sms
 * @since    5.13
 * 
 */
abstract class Billrun_Sms_Abstract {
	
	protected $to;
	
	protected $body = '';


	private function __construct($params) {
		$this->init($params);
	}
	
	public function getTo() {
		return $this->to;
	}
	
	public function setTo($to) {
		$this->to = $to;
	}
	
	public function getBody() {
		return $this->Body;
	}
	
	public function setBody($body) {
		$this->body = $body;
	}
	
	/**
	 * magic method to setup class parameters on initiation
	 * @param array $params parameters
	 * @return void
	 */
	protected function init($params) {
		foreach ($params as $key => $val) {
			if (property_exists($this, $key)) {
				$this->$key = $val;
			}
		}
	}


	public static function getInstance($params) {
		if (!isset($params['type'])) {
			$params['type'] = 'smpp';
		}
		
		$className = 'Billrun_Sms_' . ucfirst($params['type']);
		if (!class_exists($className)) {
			Billrun_Factory::log("SMS Class type is not exists. Type: " . $params['type']);
			return false;
		}
		
		unset($params['type']);
		
		return new $className($params);
	}
	
	abstract public function send();

}