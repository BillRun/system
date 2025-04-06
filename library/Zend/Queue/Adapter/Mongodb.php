<?php

require_once 'Zend/Queue/Adapter/AdapterAbstract.php';

class Zend_Queue_Adapter_Mongodb extends Zend_Queue_Adapter_AdapterAbstract {

	/**
	 * the db
	 * @var MongoDB\Db
	 */
	protected $db;
	
	/**
	 * the queue collection
	 * @var MongoDB\Collection
	 */
	protected $queueCollection;
	
	/**
	 * the message collection
	 * @var MongoDB\Collection
	 */
	protected $messageCollection;

	/**
	 * Constructor
	 *
	 * @param  array|Zend_Config $options
	 * @param  Zend_Queue|null $queue
	 * @return void
	 */
	public function __construct($options, Zend_Queue $queue = null) {
		parent::__construct($options, $queue);
		if (isset($options['db'])) {
			$this->db = $options['db'];
		}
		if (isset($options['queueCollection'])) {
			$this->queueCollection = $options['queueCollection'];
		} else {
			// throw error
			// todo initiate instance
		}
		if (isset($options['messageCollection'])) {
			$this->messageCollection = $options['messageCollection'];
		} else {
			// throw error
			// todo initiate instance
		}
	}
	
	/**
	 * Does a queue already exist?
	 *
	 * Use isSupported('isExists') to determine if an adapter can test for
	 * queue existance.
	 *
	 * @param  string $name Queue name
	 * @return boolean
	 * @todo support this function
	 */
	public function isExists($name) {
		$query = ['name' => $name];
		$result = $this->queueCollection->countDocuments($query);
		return $result > 0;
	}

	/**
	 * Create a new queue
	 *
	 * Visibility timeout is how long a message is left in the queue
	 * "invisible" to other readers.  If the message is acknowleged (deleted)
	 * before the timeout, then the message is deleted.  However, if the
	 * timeout expires then the message will be made available to other queue
	 * readers.
	 *
	 * @param  string  $name Queue name
	 * @param  integer $timeout Default visibility timeout
	 * @return boolean
	 */
	public function create($name, $timeout = null) {
        if ($this->isExists($name)) {
            return false;
        }
		$row = [
			'name' => $name,
			'timeout' => ($timeout === null) ? self::CREATE_TIMEOUT_DEFAULT : (int) $timeout
		];
		$this->queueCollection->insertOne($row);
		return true;
	}
	
	/**
	 * Delete a queue and all of its messages
	 *
	 * Return false if the queue is not found, true if the queue exists.
	 *
	 * @param  string $name Queue name
	 * @return boolean
	 */
	public function delete($name) {
		$this->queueCollection->deleteOne(['name' => $name]);
		$this->messageCollection->deleteMany(['queue_name' => $name]);
		return true;
	}

	/*
	 * Get an array of all available queues
	 *
	 * Not all adapters support getQueues(), use isSupported('getQueues')
	 * to determine if the adapter supports this feature.
	 *
	 * @return array
	 * @throws Zend_Queue_Exception - database error
	 */

	public function getQueues() {
		return iterator_to_array($this->queueCollection->find([]));
	}

	/**
	 * Return the approximate number of messages in the queue
	 *
	 * @param  Zend_Queue|null $queue
	 * @return integer
	 */
	public function count(Zend_Queue $queue = null) {
        if ($queue === null) {
            $queue = $this->_queue;
        }
		$query = ['queue_name' => $queue->getName(), 'done' => ['$ne' => 1]];
		return $this->messageCollection->countDocuments($query);
	}

	/*******************************************************************
	 * Messsage management functions
	 * ******************************************************************* */

	/**
	 * Send a message to the queue
	 *
	 * @param  mixed $message Message to send to the active queue
	 * @param  Zend_Queue|null $queue
	 * @return Zend_Queue_Message
	 */
	public function send($message, Zend_Queue $queue = null) {
        if ($queue === null) {
            $queue = $this->_queue;
        }
		
        $row = [
			'queue_name' => $queue->getName(),
			'created'    => new MongoDB\BSON\UTCDateTime(),
			'body'       => $message,
		];
		
		if (isset($message['schedule'])) {
			$row['schedule'] = new MongoDB\BSON\UTCDateTime($message['schedule'] * 1000);
			unset($row['body']['schedule']);
		}
		
		$row['md5'] = md5(serialize($row));
		
		$result = $this->messageCollection->insertOne($row);
        
		$options = array(
            'queue' => $queue,
            'data'  => $row,
        );

        $classname = $queue->getMessageClass();
        if (!class_exists($classname)) {
            require_once 'Zend/Loader.php';
            Zend_Loader::loadClass($classname);
        }
        return new $classname($options);
	}

	/**
	 * Get messages in the queue
	 *
	 * @param  integer|null $maxMessages Maximum number of messages to return
	 * @param  integer|null $timeout Visibility timeout for these messages
	 * @param  Zend_Queue|null $queue
	 * @return Zend_Queue_Message_Iterator
	 */
	public function receive($maxMessages = null, $timeout = null, Zend_Queue $queue = null) {
        if (empty($maxMessages) || !is_int($maxMessages) || $maxMessages < 0) {
            $maxMessages = 1;
        }
        if ($timeout === null) {
            $timeout = self::RECEIVE_TIMEOUT_DEFAULT;
        }
        if ($queue === null) {
            $queue = $this->_queue;
        }
        $microtime = microtime(true);
		
		$results = [];
		while ($maxMessages > 0) {
			$query = [
				'queue_name' => $queue->getName(),
				'done' => ['$ne' => 1],
				'$and' => [
					[
						'$or' => [
							['timeout' => ['$exists' => 0]],
							['timeout' => ['$lt' => new MongoDB\BSON\UTCDateTime($microtime * 1000)]],
						],
					],
					[
						'$or' => [
							['schedule' => ['$exists' => 0]],
							['schedule' => ['$gte' => new MongoDB\BSON\UTCDateTime($microtime * 1000)]],
						],
					]
				],
			];
			$update = [
				'$set' => [
					'handle' => md5(uniqid(rand(), true)),
					'start_time' => new MongoDB\BSON\UTCDateTime(),
					'timeout' => new MongoDB\BSON\UTCDateTime(($microtime+$timeout) * 1000),
				]
			];
			$sort = [
				'created' => 1,
			];
			$options = [
				'returnDocument' => \MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER, 
				'upsert' => false,
				'sort' => $sort,
			];
			$result = $this->messageCollection->findOneAndUpdate($query, $update, $options);
			if (empty($result)) {
				// no more msgs in the queue
				break;
			} else {
				$results[] = iterator_to_array($result);
			}
			$maxMessages--;
		}
        $options = array(
            'queue'        => $queue,
            'data'         => $results,
            'messageClass' => $queue->getMessageClass(),
        );

        $classname = $queue->getMessageSetClass();
        if (!class_exists($classname)) {
            require_once 'Zend/Loader.php';
            Zend_Loader::loadClass($classname);
        }
        return new $classname($options);
	}

	/**
	 * Delete a message from the queue
	 *
	 * Return true if the message is deleted, false if the deletion is
	 * unsuccessful.
	 *
	 * @param  Zend_Queue_Message $message
	 * @return boolean
	 */
	public function deleteMessage(Zend_Queue_Message $message) {
		$query = [
			'_id' => $message->_id
		];
		$update = [
			'$set' => [
				'done' => 1,
				'complete_time' => new MongoDB\BSON\UTCDateTime()
			]
		];
		$this->messageCollection->updateOne($query, $update);
		return true;
	}

	/**
	 * Return a list of queue capabilities functions
	 *
	 * $array['function name'] = true or false
	 * true is supported, false is not supported.
	 *
	 * @param  string $name
	 * @return array
	 */
	public function getCapabilities() {
		return array(
			'create' => true,
			'delete' => true,
			'send' => true,
			'receive' => true,
			'deleteMessage' => true,
			'getQueues' => true,
			'count' => true,
			'isExists' => true,
		);
	}

}
