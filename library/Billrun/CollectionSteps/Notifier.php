<?php

class Billrun_CollectionSteps_Notifier {

	protected $notifier = NULL;

	public function __construct($task, $params = array()) {
		$notifier = self::getNotifier($task, $params = array());
		if (!$notifier) {
			Billrun_Factory::log('Cannot get notifier for collection task. Details: ' . print_R($task, 1), Billrun_Log::NOTICE);
			return false;
		}
		$this->setNotifier($notifier);
	}

	public function setNotifier(Billrun_CollectionSteps_Notifiers_Strategy $notifier) {
		$this->notifier = $notifier;
	}


	public function notify() {
		if ($this->notifier) {
			return $this->notifier->notify();
		}
		return false;
	}
	
	/**
	 * Assistance function to get the notifier object based on the event
	 * 
	 * @return notifier object
	 */
	protected static function getNotifier($event, $params = array()) {
		$notifierClassName = self::getNotifierClassName($event, $params);
		if (!class_exists($notifierClassName)) {
			return false;
		}
		
		return (new $notifierClassName($event, $params));
	}
	
	/**
	 * Assistance function to get notifier name based on event parameters
	 * 
	 * @param array $event
	 * @param array $params
	 * @return string - notifier class name
	 * @todo conclude notifier class from received parameters
	 */
	protected static function getNotifierClassName($task, $params = array()) {
		$prefix = 'Billrun_CollectionSteps_Notifiers';
		$type = ucfirst(strtolower($task['step_type']));
		return "{$prefix}_{$type}";
	}
	
}
