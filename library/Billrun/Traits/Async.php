<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
declare(ticks=1);

/**
 * This Trait is used to add async capabilities
 *
 */
trait Billrun_Traits_Async {

	/**
	 * max concurrent async processes
	 * 
	 * @var int
	 */
	protected $asyncMaxConcurrent = 3;

	/**
	 * current active processes
	 * 
	 * @var int
	 */
	protected $asyncActiveProcesses = 0;

	/**
	 * default timeout in seconds
	 * 
	 * @var int
	 */
	protected $asyncTimeout = 3600;

	/**
	 * flag to know if the async was initialized
	 * 
	 * @var boolean
	 */
	protected $asyncInit = false;

	protected function initAsync() {
		if ($this->asyncInit) {
			return;
		}

		Billrun_Factory::log("initAsync triggered");

		$this->asyncInit = true;
		
		$signals = [
			SIGHUP, SIGINT, SIGQUIT, SIGILL, SIGTRAP, SIGABRT,
			SIGBUS, SIGFPE, SIGUSR1, SIGSEGV, SIGUSR2, SIGPIPE,
			SIGALRM, SIGTERM, SIGCHLD, SIGCONT, SIGTSTP, SIGTTIN, 
			SIGTTOU,
		];
		
		foreach ($signals as $signal) {
			pcntl_signal($signal, [$this, 'asyncSignalHandler']);
		}
	}

	public function asyncSignalHandler($signo) {
		switch ($signo) {
			case SIGCHLD:
				// Decrement activeProcesses for each terminated child process
				while (($pid = pcntl_waitpid(-1, $status, WNOHANG)) > 0) {
					$this->asyncActiveProcesses--;
				}
				break;
			case SIGALRM:
				Billrun_Factory::log("asyncSignalHandler signo: " . $signo . " Process timed out");
				exit(1); // Exit with error status
				break;
            default:
				Billrun_Factory::log("asyncSignalHandler signo: " . $signo);

		}
	}

	public function setAsyncMaxConcurrent($max = null) {
		$this->asyncMaxConcurrent = $max;
	}

	public function setAsyncTimeout($timeout = null) {
		$this->asyncTimeout = $timeout;
	}

	protected function checkAsyncConcurrentLimit() {
		while ($this->asyncActiveProcesses >= $this->asyncMaxConcurrent) {
			Billrun_Factory::log("checkAsyncConcurrentLimit going to wait for pid to finish");
			pcntl_waitpid(-1, $status); // Wait for any child process to exit
			Billrun_Factory::log("checkAsyncConcurrentLimit pcntl_waitpid status " . $status);
			$this->asyncActiveProcesses--;
		}
	}

	/**
	 * function to wait for all async processes to be finished
	 * 
	 * @todo handle async processes timeout
	 */
	public function wait() {
		// Wait for all child processes to finish
		while ($this->asyncActiveProcesses > 0) {
			pcntl_waitpid(-1, $status); // Wait for any child process to exit
			$this->asyncActiveProcesses--;
		}
	}

	public function executeAsync(callable $task, $args = array()) {
		if (!RUNNING_FROM_CLI) {
			Billrun_Factory::log("Cannot fork if not running from CLI; run process without fork");
			call_user_func_array($task, $args);
			return;
		}
		
		if (Billrun_Factory::config()->getConfigValue('async.fork.disabled', 0)) {
			Billrun_Factory::log("Fork is disabled. Running in sync mode");
			call_user_func_array($task, $args);
			return;
		}

		$this->initAsync();
		$this->checkAsyncConcurrentLimit(); // this will hold the process until another process will be finished
		$pid = pcntl_fork();
		if ($pid == -1) {
			die("Failed to fork process.");
		} elseif ($pid) {
			// Parent process
			// Do nothing, let the child process execute the task
			Billrun_Factory::log("forked child process: " . $pid);
			$this->asyncActiveProcesses++;
		} else {
			// Child process
			Billrun_Factory::db([], true)->command(['ping' => 1]);
			Billrun_Jobsmanager::cleanInstance();
			Billrun_Factory::log()->updateStamp();
			Billrun_Factory::log("child process");
			pcntl_alarm($this->asyncTimeout);
			call_user_func_array($task, $args);
			Billrun_Factory::log("child process finished");
			pcntl_alarm(0);
			exit(0);
		}
	}
}
