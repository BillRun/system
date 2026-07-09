<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Job Abstract Class
 *
 * @package  Job Manager
 * @since    5.16
 */
abstract class Billrun_Job_Abstract {

	protected $name = 'Job';
	
	/**
	 * the message queue
	 * 
	 * @var Zend_Queue_Message
	 */
	protected $queueMsg;
	
	/**
	 * configuration for the job
	 * @var array
	 */
	protected $config;
	
	/**
	 * the data container
	 * 
	 * @var mixed
	 */
	protected $data;
	
	/**
	 * how many time the job will be retried
	 * @var int
	 */
	protected $limitRuns = 3;

	/**
	 * parent md5 if this is a child job
	 * @var string
	 */
	protected $parent;

	public function __construct(Zend_Queue_Message $queueMsg) {
		$this->queueMsg = $queueMsg;
		if (isset($queueMsg->body['config'])) {
			$this->config = Mongodloid_Result::getResult($queueMsg->body['config']);
		}
		if (isset($queueMsg->body['parent'])) {
			$this->parent = $queueMsg->body['parent'];
		}
	}
	
	public function __get($param) {
		if (property_exists($this, $param)) {
			return $this->$param;
		}
	}
	
	public function markCompleted() {
		if (empty($this->queueMsg)) {
			return;
		}
		if ($this->finished() !== true) {
			return;
		}
		Billrun_Factory::log("Mark job " . $this->queueMsg->handle . " as done");
		return Billrun_Factory::queue('jobs')->deleteMessage($this->queueMsg);
	}
	
	/**
	 * method that triggered after the job has been finished
	 * 
	 * @return bool true if finished success else return false and the job will not be completed
	 */
	protected function finished() {
		return true;
	}
	
	protected function init($params) {
		return true;
	}


	protected function fetch() {
		return [];
	}

	/**
	 * job main execution method
	 */
	public function execute($params) {
		$this->init($params);
		if (!$this->runLimitExceed()) {
			$this->data = $this->fetch();
			$this->run();
		}
		$this->markCompleted();
	}
	
	/**
	 * check if the number of run of the same job exceed the limit
	 * @return type
	 */
	protected function runLimitExceed() {
		return $this->queueMsg->try > $this->limitRuns;
	}

	abstract protected function run();
}
