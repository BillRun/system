<?php

class Collect_TaskManager{
	
	protected $action = NULL;
	protected $task = NULL;
 
    public function __construct($task) {
		$action = null;
		
		switch ($task['type']) {
			case 'mail': 
				$action = new Collect_Actions_Mail();
				break;
			case 'sms':
				$action = new Collect_Actions_Sms();
				break;
			default:
				error_log(__FILE__ . '(' . __FUNCTION__ . ":" . __LINE__ . ") " . "\n" . "unsuport collection task" . " :\n" . print_r($task, 1) . "\n");
				break;
		}
		$this->setTaskData($task);
		$this->setAction($action);
	}
	
	public function setAction(Collect_TaskStrategy $collect_Action) {
        $this->action = $collect_Action;
    }
	
	public function setTaskData($task) {
        $this->task = $task;
    }
	
	public function run(){
		return $this->action->run($this->task);
	}
	
}