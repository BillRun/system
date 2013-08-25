<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing log class
 * Based on Zend Log class
 *
 * @package  Billing
 * @since    0.5
 */
class Billrun_Log extends Zend_Log {

	/**
	 * the log instances for bridged singletones
	 * 
	 * @var array
	 */
	protected static $instances = array();
	
	/**
	 * stamp of the run (added to separate processes while running to the same log file)
	 * @var string
	 */
	protected $stamp = '';

    public function __construct(Zend_Log_Writer_Abstract $writer = null) {
		parent::__construct($writer);
		$this->stamp = substr(md5($_SERVER['REQUEST_TIME']), 0, 7);
	}
	
	public static function getInstance(array $options = array()) {

		$stamp = md5(serialize($options));
		if (!isset(self::$instances[$stamp])) {
			if (empty($options)) {
				$config = Yaf_Application::app()->getConfig();
				$options = $config->log->toArray();
			}
			self::$instances[$stamp] = Billrun_Log::factory($options);
		}

		return self::$instances[$stamp];
	}

	/**
	 * Log a message at a priority
	 *
	 * @param  string   $message   Message to log
	 * @param  integer  $priority  Priority of message
	 * @param  mixed    $extras    Extra information to log in event
	 * @return void
	 * @throws Zend_Log_Exception
	 */
	public function log($message, $priority = Zend_Log::DEBUG, $extras = null) {
		if ($this->stamp) {
			$message = '[' . $this->stamp . '] ' . $message;
		}
		
		parent::log($message, $priority, $extras);
	}

}