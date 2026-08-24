<?php

require_once 'Zend/Queue/Adapter/AdapterAbstract.php';

/**
 * This class is experimental
 */
class Zend_Queue_Adapter_Redis extends Zend_Queue_Adapter_AdapterAbstract {

	protected $_redis;
	protected $_options = [
		'host' => '127.0.0.1',
		'port' => 6379,
		'queue_name' => 'zend_queue',
	];

	public function __construct(array $options = [], Zend_Queue $queue = null) {
		if (!extension_loaded('redis')) {
			throw new Zend_Queue_Exception('Redis extension is required.');
		}

		parent::__construct($options, $queue);

		$this->_redis = new Redis();
		$this->_redis->connect($this->_options['host'], $this->_options['port']);

		if (!empty($this->_options['password'])) {
			if (!$this->_redis->auth($this->_options['password'])) {
				throw new Zend_Queue_Exception('Failed to authenticate with Redis.');
			}
		}

		if (!empty($this->_options['database'])) {
			$this->_redis->select((int) $this->_options['database']);
		}
	}

	public function getCapabilities() {
		return [
			'create' => true,
			'delete' => true,
			'send' => true,
			'receive' => true,
			'deleteMessage' => false,
			'getQueues' => true,
			'createQueue' => true,
			'deleteQueue' => true,
			'isExists' => true,
			'count' => true,
		];
	}

	public function create($name, $timeout = null) {
		// Redis lists are created automatically when pushing a value,
		// so there's no need for explicit creation.
		return true;
	}

	public function delete($name) {
		return (bool) $this->_redis->del($name);
	}

	public function send($message, array $options = []) {
		return $this->_redis->rPush($this->_options['queue_name'], $message);
	}

	public function receive($maxMessages = 1, $timeout = null) {
		$messages = [];
		for ($i = 0; $i < $maxMessages; $i++) {
			$message = $this->_redis->lPop($this->_options['queue_name']);
			if ($message === false) {
				break;
			}
			$messages[] = new Zend_Queue_Message(
				['message_id' => null, 'body' => $message],
				$this->_queue
			);
		}
		return $messages;
	}

	public function deleteMessage(Zend_Queue_Message $message) {
		// Messages are removed from the queue upon receipt (lPop),
		// so explicit deletion is not typically required.
		return true;
	}

	public function count() {
		return $this->_redis->lLen($this->_options['queue_name']);
	}

	public function getQueues() {
		// Assuming all keys in Redis are queues; adjust the pattern as needed.
		return $this->_redis->keys('*');
	}

	public function createQueue($name, $timeout = null) {
		// Redis lists are created automatically when pushing a value,
		// so there's no need for explicit creation.
		return true;
	}

	public function deleteQueue($name) {
		return (bool) $this->_redis->del($name);
	}

	public function isExists($name) {
		return (bool) $this->_redis->exists($name);
	}
}
