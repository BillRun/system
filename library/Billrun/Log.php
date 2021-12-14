<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
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
	 * login user
	 * 
	 * @var string
	 */
	protected $user = null;

	/**
	 * Class constructor.  Create a new logger.
	 * Generate an ID for the logger for later filtering.
	 *
	 * @param Zend_Log_Writer_Abstract|null  $writer  default writer
	 * @return void
	 */
	public function __construct(Zend_Log_Writer_Abstract $writer = null) {
		parent::__construct($writer);

		$this->updateStamp();

		if (isset($_SERVER['REQUEST_URI']) && stripos($_SERVER['REQUEST_URI'], 'realtime') === FALSE && ($user = Billrun_Factory::user()) !== FALSE) {
			$this->user = $user->getUsername();
		}
	}

	public static function getInstance(array $options = array()) {

		$stamp = md5(serialize($options));
		if (!isset(self::$instances[$stamp])) {
			if (empty($options)) {
				$options = Billrun_Factory::config()->getConfigValue('log');
			}
			self::$instances[$stamp] = Billrun_Log::factory($options);
		}

		return self::$instances[$stamp];
	}

	/**
	 * Log a crash using raised exception as crash details.
	 * 
	 * @param exception $e - The exception object that was raised.
	 * @param LogPriority $priority - The priority of the crash, Critical by default.
	 */
	public function logCrash($e, $priority = Billrun_Log::CRIT) {
		$log = print_R($_SERVER, TRUE) . PHP_EOL .
				print_R('Exception type : ' . get_class($e) . PHP_EOL .
						'Error code : ' . $e->getCode() . PHP_EOL .
						'Error message: ' . $e->getMessage() . PHP_EOL . 'Host: ' .
						gethostname() . PHP_EOL . $e->getTraceAsString(), TRUE);
		$this->log('Crashed When running... exception details are as follow : ' . PHP_EOL . $log, $priority);
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
		$prefixes = array();
		if ($this->stamp) {
			$prefixes[] = $this->stamp;
		}

		if ($this->user) {
			$prefixes[] = $this->user;
		}

		if (!empty($prefixes)) {
			$message = '[' . implode('|', $prefixes) . '] ' . $message;
		}
		parent::log($message, $priority, $extras);
	}

	public function removeWriters($writerName) {
		$log = Billrun_Factory::config()->getConfigValue('log', array());
		if (!$log) {
			error_log("removeWriters Log is null!\n");
			return;
		}

		foreach ($log as $writer) {
			// Check if in the correct format.
			if (!is_array($writer)) {
				continue;
			}

			// Found the writer to remove.
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

	public function addWriters($writerName) {
		$log = Billrun_Factory::config()->getConfigValue('log', array());
		if (!$log) {
			error_log("addWriters Log is null!\n");
			return;
		}

		foreach ($log as $writer) {
			// If the writer is in the correct format.
			if (is_array($writer) && $writer['writerName'] == $writerName) {
				$this->addWriter($writer);
			}
		}
	}

	protected function _packEvent($message, $priority) {
		return array_merge(array(
			'timestamp' => date($this->_timestampFormat) . ':' . (substr(($microtime = microtime(0)), 0, strpos($microtime, ' ')) * 1000),
			'message' => $message,
			'priority' => $priority,
			'priorityName' => $this->_priorities[$priority]
				), $this->_extras
		);
	}

	public function updateStamp() {
		if ($pid = getmypid()) {
			$this->stamp = Billrun_Factory::config()->getTenant() . ':' . Billrun_Util::getHostName() . ':p' . $pid;
		} else {
			// Make a unique log stamp for each run of the application
			$this->stamp = Billrun_Factory::config()->getTenant() . ':' . substr(md5($_SERVER['REQUEST_TIME'] . rand(0, 100)), 0, 7);
		}
	}

}
