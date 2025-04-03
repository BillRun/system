<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Job Manager
 *
 * @package  Job Manager
 * @since    5.16
 */
class Billrun_Jobsmanager {

	protected $method = 'jobmanager';
	
	/**
	 * array container of singleton
	 * @var array
	 */
	static protected $instances = [];
	
	/**
	 * the queue that job is managed in
	 * 
	 * @var Zend_Queue
	 */
	protected $queue;
	
	/**
	 * the timeout of the job to be pulled before the same job will be pulled again if not done
	 * 
	 * @var type int
	 */
	protected $timeout = 3600;
	
	/**
	 * the timeout of the job to be pulled before the same job will be pulled again if not done
	 * 
	 * @var type int
	 */
	protected $lastError = '';

	protected function __construct($params = []) {
		foreach ($params as $k => $v) {
			if (property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
	}

	/**
	 * push job into queue 
	 * @param string $type the type of the job
	 * @param array $config the configuration of the job
	 * @param string $parent the parent task if available
	 * @param int $schedule schedule the job for future; unix timestamp
	 * @return type
	 */
	public function push($type, $config, $parent = null, $schedule = null) {
		$this->lastError = '';
		if (!$this->validateInput($type, $config)) {
			return false;
		}
		$jobSettings = ['type' => $type, 'config' => $config];
		if (!empty($parent)) {
			$jobSettings['parent'] = $parent;
		}
		
		if (!empty($schedule)) {
			if (!is_numeric($schedule)) { // let's convert it to unix timestamp
				$jobSettings['schedule'] = strtotime($schedule);
			} else {
				$jobSettings['schedule'] = (int) $schedule;
			}
		}

		$msg = $this->queue->send($jobSettings);
		return Mongodloid_Result::getResult($msg->toArray());
	}
	
	protected function validateInput($job_type, $config) {
		if (!class_exists('Billrun_Job_' . $job_type)) {
			$this->lastError = "job type is not exists";
			Billrun_Factory::log($this->lastError, Zend_Log::WARN);
			return false;
		}
		return true;
	}
	
	public function getLastError() {
		$ret = $this->lastError;
		$this->lastError = '';
		return $ret;
	}

	public function pull() {
		$jobsQueue = $this->queue->receive(1, $this->timeout);
		foreach ($jobsQueue as $k => $entry) {
			if (empty($entry->body['type'])) {
				return false;
			}
			$class = 'Billrun_Job_' . (string) $entry->body['type'];
			$job = new $class($entry);
		}
		return $job ?? false;
	}

	/**
	 * singletone of queue instance
	 * 
	 * @param Zend_Queue $queue the queue that the job manager is running on
	 * 
	 * @return Billrun_Jobsmanager
	 */
	public static function getInstance($queue = null) {
		if (empty($queue)) {
			$queue = Billrun_Factory::queue('jobs');
		}
		$queueName = $queue->getName();
		if (empty(self::$instances[$queueName])) {
			self::$instances[$queueName] = new self(['queue' => $queue, 'timeout' => $queue->getOption('timeout')]);
		}
		return self::$instances[$queueName];
	}
	
	/**
	 * check if the worker is enabled
	 * 
	 * @return boolean true if enabled else false
	 */
	public function isWorkerEnabled() {
		return Billrun_Factory::config()->getConfigValue('worker.enabled', false);
	}

}
