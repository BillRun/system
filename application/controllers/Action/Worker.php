<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2021 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Worker action controller class
 *
 * @package     Controllers
 * @subpackage  Action
 */
class WorkerAction extends Action_Base {
	
	use Billrun_Traits_Async;

	protected $startTime;
	protected $queue;

    /**
     * method to execute the worker process
	 * this action should supervised to be up and running
     * 
     */
    public function execute() {
		Billrun_Factory::log("Start worker");
		$this->startTime = time();
		$this->queue = Billrun_Factory::queue('jobs', Billrun_Factory::config()->getConfigValue('worker.job_timeout', 3600));
		$this->setAsyncMaxConcurrent(Billrun_Factory::config()->getConfigValue('worker.concurrent_limit', 10));
		Billrun_Factory::log("Queue " . $this->queue->getName() . " loaded with count of " . $this->queue->count());
//		Billrun_Jobsmanager::getInstance($this->queue)->push('Charging_Account', ['aids' => [1]]);die;
		$this->run();
	}

	/**
	 * method to run and enable the worker to receive and process jobs
	 */
	protected function run() {
		while($this->checkIteration()) {
			try {
				// fetch job from the job queue
				Billrun_Factory::log("Run iteration");
				$job = Billrun_Jobsmanager::getInstance($this->queue)->pull();
				if (!empty($job)) {
					Billrun_Factory::log("Going to execute job " . $job->method . " handle " . $job->queueMsg->handle, Zend_Log::INFO);
					$this->executeAsync(array($job, 'execute'), [['config' => (array) $job->config]]);
				}
			} catch (Throwable $th) {
				Billrun_Factory::log("Worker error: " . $th->getCode() . ": " . $th->getMessage(), Zend_Log::ALERT);
			}
			usleep(Billrun_Factory::config()->getConfigValue('worker.iteration', 200000)); // sleep 0.2 second
		}
	}
	
	/**
	 * method to run and enable the worker based on cron to receive and process jobs
	 */
	protected function checkIteration() {
		if (!Billrun_Factory::config()->getConfigValue('worker.cron.enabled')) {
			return true;
		}
		
		$iterationTimeout = Billrun_Factory::config()->getConfigValue('worker.cron.timeout', 55); // 1 minute - 5 seconds
		if (time() - $iterationTimeout > $this->startTime) {
			return false;
		} else {
			return true;
		}
	}

}