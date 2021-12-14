<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * handle of sending 
 *
 */
class Billrun_EmailSenderManager {

	/**
	 * @var Billrun_EmailSenderManager
	 */
	protected static $instance;

	/**
	 * @var $params
	 */
	protected $params = array();

	private function __construct($params = array()) {
		$this->params = $params;
	}

	public static function getInstance($params = array()) {
		if (is_null(self::$instance)) {
			self::$instance = new Billrun_EmailSenderManager($params);
		}
		return self::$instance;
	}

	/**
	 * send emails
	 */
	public function notify($callback = false) {
		$emailSender = Billrun_EmailSender_Manager::getInstance($this->params);
		$emailSender->notify($callback);
	}

}
