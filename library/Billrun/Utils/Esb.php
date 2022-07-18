<?php
/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This a class for queue interfaces based on Stomp use.
 * can use sengMsg and getMsg to communicate with the queue (after subscribing to a given queue)
 * @package  Util
 */
class Billrun_Utils_Esb {

	protected $stompClient = null;
	protected $queueConfig = [];

	function __construct($options = array()) {
		$queue_config = $options['queue_config'] ?? array();
		$this->queueConfig = array_merge(Billrun_Factory::config()->getConfigValue('esb.queue_config', array()), $queue_config);
		$host = $this->queueConfig['host'] ?? '';
		$port = $this->queueConfig['port'] ?? '';
		$user = $this->queueConfig['user'] ?? '';
		$pass = $this->queueConfig['pass'] ?? '';
		Billrun_Factory::log()->log("Connecting to Message Broker", Zend_Log::INFO);
		// Check if Stomp class exists
		$classname = 'Stomp';
		if (!@class_exists($classname)) {
			throw new Exception('Something went wrong while trying to connect to Message Broker. Could not find class: ' . $classname);
		}
		if (empty($host)) {
			throw new Exception('Something went wrong while trying to connect to Message Broker. Host empty.');
		}
		try {
			$this->stompClient = new $classname('tcp://' . $host . ":" . $port, $user, $pass);
		} catch (Exception $ex) {
			throw new Exception('Something went wrong while trying to connect to Message Broker. Esb Recieve Error : ' . $ex->getMessage());
		}
	}

	/**
	 * Send a message to the ESB on a given queue.
	 */
	public function sendMsg($msg, $queueName, $headers = []) {
		Billrun_Factory::log()->log('Sending message to queue: ' . $queueName, Zend_Log::INFO);
		Billrun_Factory::log()->log('Message: ' . $msg, Zend_Log::INFO);
		if (!isset($this->stompClient)) {
			throw new Exception('Something went wrong while trying to Send message to queue: ' . $queueName . '. Stomp not exist');
		}
		try {
			return $this->stompClient->send($queueName, $msg, $headers);
		} catch (Exception $e) {
			throw new Exception('Something went wrong while trying to Send message to queue: ' . $queueName . 'Esb send Error : ' . $e->getMessage());
		}
	}

	/**
	 * Get Messages  from the ESB for a given queue.
	 */
	public function getMsg($queueName, $waitTime = 86400000, $ack = TRUE) {
		Billrun_Factory::log()->log("Geting messages from queue: " . $queueName, Zend_Log::INFO);
		if (!isset($this->stompClient)) {
			throw new Exception('Something went wrong while trying to get messages from queue: ' . $queueName . '. Stomp not exist');
		}
		do {
			$starttime = microtime(true);
			$this->stompClient->setReadTimeout($waitTime / 1000);
			try {
				if ($this->stompClient->hasFrame()) {
					$esbFrame = $this->stompClient->readFrame();
					$inQname = $this->getActionFromMsgHeaders($esbFrame);
					if ($inQname == $queueName) {
						if ($ack) {
							$this->stompClient->ack($esbFrame);
						}
						$messages = $esbFrame->body;
						Billrun_Factory::log()->log("messages: " . print_r($messages, true), Zend_Log::INFO);
						return $messages;
					}
				}
			} catch (Exception $e) {
				throw new Exception('Something went wrong while trying to get messages from queue: ' . $queueName . '. Esb Recieve Error : ' . $e->getMessage());
			}
			$waitTime -= microtime(true) - $starttime;
		} while ($waitTime >= 0);
		return FALSE;
	}

	/**
	 * Register to given queues on the ESB
	 */
	public function subscribeToQueues($queues, $headers = array()) {
		Billrun_Factory::log()->log('Registering to queues: ' . print_r($queues, true), Zend_Log::INFO);
		if (!isset($this->stompClient)) {
			throw new Exception('Something went wrong while trying to register to given queues. Stomp not exist');
		}
		foreach ($queues as $qname) {
			$this->stompClient->subscribe($qname, $headers);
		}
	}

	/**
	 * Get the queue name from a received  message header 
	 * @param type $esbFrame 
	 * @return type
	 */
	protected function getActionFromMsgHeaders($esbFrame) {
		return preg_replace('/(\/queue\/|\/' . str_replace('/', '\/', $this->queueConfig['queue_prefix']) . '\/|\/in|\/)/', '', $esbFrame->headers['destination']);
	}
}
