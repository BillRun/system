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

	protected $name = 'job';
	
	/**
	 * the message queue
	 * 
	 * @var array
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

	public function __construct(Zend_Queue_Message $queueMsg) {
		$this->queueMsg = $queueMsg;
		if (isset($queueMsg->body['config'])) {
			$this->config = Mongodloid_Result::getResult($queueMsg->body['config']);
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
		Billrun_Factory::log("Mark job " . $this->queueMsg->handle . " as done");
		return Billrun_Factory::queue('jobs')->deleteMessage($this->queueMsg);
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
		$this->data = $this->fetch();
		$this->run();
		$this->markCompleted();
	}
	
	abstract protected function run();
}
