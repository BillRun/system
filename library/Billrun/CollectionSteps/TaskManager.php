<?php

class Billrun_CollectionSteps_TaskManager {

	protected $action = NULL;
	protected $task = NULL;

	public function __construct($task) {
		$action = null;

		switch ($task['type']) {
			case 'mail':
				$action = new Billrun_CollectionSteps_Actions_Mail();
				break;
			case 'sms':
				$action = new Billrun_CollectionSteps_Actions_Sms();
				break;
			default:
				Billrun_Factory::log("Unsuport collection task: " . print_r($task, 1), Zend_Log::ALERT);
				return;
		}
		$this->setTaskData($task);
		$this->setAction($action);
	}

	public function setAction(Billrun_CollectionSteps_TaskStrategy $collect_Action) {
		$this->action = $collect_Action;
	}

	public function setTaskData($task) {
		$this->task = $task;
	}

	public function run() {
		if ($this->action) {
			return $this->action->run($this->task);
		}
		return false;
	}

}
