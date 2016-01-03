<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
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
	
	const DUPLICATE_MESSAGE_SUPPRESS_COUNTER = 50;
	const DUPLICATE_MESSAGE_PRIORITY_THRESHOLD = Zend_Log::DEBUG;
	const LOG_MSG_PREFIX_SIZE = 7;
	
	const LOG_COLOR_RED = 1;
	const LOG_COLOR_GREEN = 2;
	const LOG_COLOR_YELLOW = 3;
	
	/**
	 * Array for the log messages prefixes based on priority.
	 */
	private static $PRIORITY_COLORS = array
		(
			Zend_Log::EMERG => Billrun_Log::LOG_COLOR_RED,
			Zend_Log::ERR => Billrun_Log::LOG_COLOR_RED,
			Zend_Log::CRIT => Billrun_Log::LOG_COLOR_RED,
			Zend_Log::WARN => Billrun_Log::LOG_COLOR_YELLOW,
//			Zend_Log::DEBUG => Billrun_Log::LOG_COLOR_YELLOW,
//			Zend_Log::INFO => Billrun_Log::LOG_COLOR_RED,
//			Zend_Log::NOTICE => "NOTIC ",
//			Zend_Log::ALERT => "ALERT "		
		);
	
	/**
	 * Add color to a string.
	 * 
	 * @string - String to put color on.
	 * @color - int representing the requested.
	 */
	protected function addColor($string, $color) {
		$fixedColor = $color %10;
		return "\033[1;3" . $fixedColor . "m" . $string . "\033[0m";
	}
	
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
	 * If true log is printed in color.
	 * @var type Boolean
	 */
	protected $colored = true;
	
	/**
	 * This stores the last message that was logged for log suppressing.
	 * 
	 * @var string - Last message that was logged.
	 */
	private $lastMessage = null;
	
	/**
	 * This stores the priority of the last message that was logged for log suppressing.
	 * 
	 * @var priority - Priority of the last message that was logged.
	 */
	private $lastMessagePriority = Zend_Log::DEBUG;
	
	/**
	 * This stores the extras of the last message that was logged for log suppressing.
	 * 
	 * @var extras - Extras of the last message that was logged.
	 */
	private $lastMessageExtras = null;
	
	/**
	 * This is to print how many duplicate messages were suppressed.
	 * @var int - Counter for how many duplicate messages were printed.
	 */
	private $duplicateMessageCounter = 0;
	
    /**
     * Class constructor.  Create a new logger.
	 * Generate an ID for the logger for later filtering.
     *
     * @param Zend_Log_Writer_Abstract|null  $writer  default writer
	 * @param Boolean $colored If true log is printed in color.
     * @return void
     */
    public function __construct(Zend_Log_Writer_Abstract $writer = null, $colored=true) {
		parent::__construct($writer);
		$this->colored = $colored;
		
		if ($pid = getmypid()) {
			$this->stamp = Billrun_Util::getHostName() .  ':p' . $pid;
		} else {
			// Make a unique log stamp for each run of the application
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

//	/**
//	 * Overriding the Zend log _packEvent function to print with colors.
//	 * @param type $message
//	 * @param type $priority
//	 * @return type
//	 */
//	protected function _packEvent($message, $priority) {
//		$logRecord = parent::_packEvent($message, $priority);
//		if($this->colored && array_key_exists($priority, Billrun_Log::$PRIORITY_COLORS)){
//			// item is in the hastable
//			$logRecord['priorityName'] = 
//				str_pad($this->addColor($logRecord['priorityName'], 
//										Billrun_Log::$PRIORITY_COLORS[$priority]), 
//					    Billrun_Log::LOG_MSG_PREFIX_SIZE, " ");
//		}
//		
//		return $logRecord;
//	}

	/**
	 * Log a crash using raised exception as crash details.
	 * 
	 * @param exception $e - The exception object that was raised.
	 * @param LogPriority $priority - The priority of the crash, Critical by default.
	 */
	public function logCrash($e, $priority=Billrun_Log::CRIT) {
		$log = 
			print_R($_SERVER, TRUE) . PHP_EOL . 
			print_R('Error code : ' . $e->getCode() . PHP_EOL . 
					'Error message: ' . $e->getMessage() . PHP_EOL . 'Host: ' .
					gethostname() . PHP_EOL . $e->getTraceAsString(), TRUE); 
		$this->log('Crashed When running... exception details are as follow : ' . PHP_EOL . $log, $priority);
	}
	
	/**
	 * This function executes logic for suppressing duplicate log prints, 
	 * return true if there is a message to print and false if there is non to be printed.
	 * 
	 * @param  string   $message   Message to log
	 * @param  integer  $priority  Priority of message
	 * @param  mixed    $extras    Extra information to log in event
	 * 
	 * @return boolean false if nothing to be printed.
	 */
	protected function logSuppressDuplicates($message, $priority, $extras) {
		// Check if the priority is to be suppressed.
		if($priority < Billrun_Log::DUPLICATE_MESSAGE_PRIORITY_THRESHOLD){
			return true;
		}
		
		$duplicateMessage = false;
		
		// Check if this message is the same as the last one logged.
		if($message  == $this->lastMessage		   && 
		   $priority == $this->lastMessagePriority &&
		   $extras   == $this->lastMessageExtras){
			$this->duplicateMessageCounter++;
			
			// Check if to suppress the message.
			if($this->duplicateMessageCounter < Billrun_Log::DUPLICATE_MESSAGE_SUPPRESS_COUNTER) {
				return false;
			}
			
			$duplicateMessage = true;
		}
		
		$tempLastMessage = $this->lastMessage;
					
		if($this->duplicateMessageCounter > 0) {
			$suppressedMessage = $this->lastMessage . " (Suppressed $this->duplicateMessageCounter)";
			$this->duplicateMessageCounter = 0;
			
			// Print the last message if exists.
			$this->log($suppressedMessage, $this->lastMessagePriority, $this->lastMessageExtras);
		}
		
		// Set the last message to be the last logged not including the 'Suppressed' suffix.
		if ($duplicateMessage) {
			$this->lastMessage = $tempLastMessage;
			return false;
		}
		
		// There is no need to print again, if this is also a duplicated message.
		$this->lastMessage = $message;
		$this->lastMessagePriority = $priority;
		$this->lastMessageExtras = $extras;
		
		return true;
	}
	
	/**
	 * Log a message at a priority
	 *
	 * @param  string   $message   Message to log
	 * @param  integer  $priority  Priority of message
	 * @param  mixed    $extras    Extra information to log in event
	 * @param  color	$color	   Number for ASCII color for the message to be printed in, NULL if no color.
	 * @return void
	 * @throws Zend_Log_Exception
	 */
	public function log($message, $priority = Zend_Log::DEBUG, $extras = null, $color=null) {
		if($color !== null){
			$message = $this->addColor($message, $color);
		}

		// If returned false, nothing to be printed.
		if(!$this->logSuppressDuplicates($message, $priority, $extras)) {
			return;
		}
		
		if ($this->stamp) {
			$message = '[' . $this->stamp . '] ' . $message;
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

}
