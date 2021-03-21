<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing dispatcher class
 *
 * @package  Dispatcher
 * @since    0.5
 */
class Billrun_Dispatcher extends Billrun_Spl_Subject {

	/**
	 * dispatcher singleton instance (singleton)
	 *
	 * @var Billrun_Dispatcher
	 */
	static protected $instance = array();

	/**
	 * arguments send to the observers
	 *
	 * @var array
	 */
	protected $args = array();

	/**
	 * the event which trigger to the observers
	 *
	 * @var string
	 */
	protected $event;

	/**
	 * used for nested running of dispatcher
	 * 
	 * @var int
	 */
	static protected $run = 0;

	/**
	 * Singleton/Bridge pattern
	 * By default it will take self instance
	 * If require special dispatcher type will be passed in the params array
	 *
	 * @param array $params parameters of the instance
	 * @return type
	 */
	public static function getInstance(array $params = array()) {
		if (isset($params['type'])) {
			if (!isset(self::$instance[$params['type']])) {
				settype($params['type'], 'string');
				$dispatcher = 'Billrun_Dispatcher_' . ucfirst($params['type']);
				self::$instance[$params['type']] = new $dispatcher();
			}
			return self::$instance[$params['type']];
		}

		$instance_id = 'default' . self::$run;
		if (!isset(self::$instance[$instance_id])) {
			if (self::$run == 0) {
				self::$instance[$instance_id] = new Billrun_Dispatcher();
			} else {
				self::$instance[$instance_id] = clone self::$instance['default0'];
			}
		}
		return self::$instance[$instance_id];
	}

	/**
	 * Triggers an event by dispatching arguments to all observers that handle
	 * the event and returning their return values.
	 *
	 * @return  array  An array of results from each function call.
	 *
	 */
	public function notify() {
		$ret = array();
		self::$run++;
		foreach ($this->observers as $observer) {
			$plugin_class = get_class($observer);
			$ret[$plugin_class] = $observer->update($this);
		}
		self::$run--;
		return $ret;
	}

	/**
	 * Triggers an event by dispatching arguments to all observers that handle
	 * the event and returning their return values.
	 *
	 * @param   string  $event  The event to trigger.
	 * @param   array   $args   An array of arguments.
	 *
	 * @return  array  An array of results from each function call.
	 *
	 */
	public function trigger($event, $args = array()) {
		// set the event and the args, they will be used by the observers (plugins)
		$this->setEvent($event)->setArgs($args);

		// notify all observer about the event triggered
		return $this->notify();
	}

	/**
	 * method to get the arguments of the object
	 *
	 * @return array the arguments of the object
	 */
	public function getArgs() {
		return $this->args;
	}

	/**
	 * method to set the arguments of the object
	 *
	 * @param array $args arguments to set the object
	 *
	 * @return Dispatcher self instance
	 */
	protected function setArgs(array $args) {
		$this->args = $args;
		return $this;
	}

	/**
	 * method to get the event of the object
	 *
	 * @return string the event of the object
	 */
	public function getEvent() {
		return $this->event;
	}

	/**
	 * method to set the event of the object
	 *
	 * @param string $event event to set the object
	 *
	 * @return Dispatcher self instance
	 */
	protected function setEvent($event) {
		$this->event = $event;
		return $this;
	}

}
