<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
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

	/**
	 * Class constructor.  Create a new logger
	 *
	 * @param Zend_Log_Writer_Abstract|null  $writer  default writer
	 * @return void
	 */
	public function __construct(Zend_Log_Writer_Abstract $writer = null) {
		parent::__construct($writer);
		if ($pid = Billrun_Util::getPid()) {
			$this->stamp = Billrun_Util::getHostName() .  ':p' . $pid;
		} else {
			$this->stamp = substr(md5($_SERVER['REQUEST_TIME'] . rand(0, 100)), 0, 7);
		}
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

	public function removeWriters($writerName) {
		$log = Billrun_Factory::config()->getConfigValue('log', array());
		if ($log) {
			foreach ($log as $writer) {
				if (is_array($writer)) {
					if ($writer['writerName'] == $writerName) {
						$className = $this->getClassName($writer, "writer", $this->_defaultWriterNamespace);
						foreach ($this->_writers as $writerIndex => $writer) {
							if (get_class($writer) == $className) {
								unset($this->_writers[$writerIndex]);
							}
						}
						break;
					}
				}
			}
		}
	}

	public function addWriters($writerName) {
		$log = Billrun_Factory::config()->getConfigValue('log', array());
		if ($log) {
			foreach ($log as $writer) {
				if (is_array($writer) && $writer['writerName'] == $writerName) {
					$this->addWriter($writer);
				}
			}
		}
	}

}
