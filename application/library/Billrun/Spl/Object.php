<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing Spl Object
 *
 * @package  SPL
 * @since    1.0
 */
class Billrun_Spl_Object implements SplSubject {

	/**
	 * the observers that can fire events
	 * 
	 * @var array
	 */
	protected $observers = array();

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
	 * Attach an observer object
	 *
	 * @param   SplObserver  $observer  An observer object to attach
	 *
	 * @return  void
	 */
	public function attach(SplObserver $observer) {
		$this->observers[] = $observer;
	}

	/**
	 * Detach an observer object
	 *
	 * @param   SplObserver  $observer  An observer object to detach.
	 *
	 * @return  boolean  True if the observer object was detached.
	 *
	 */
	public function detach(SplObserver $observer) {
		$key = array_search($observer, $this->observers);
		if ($key) {
			unset($this->observers[$key]);
			return TRUE;
		}
		return FALSE;
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
	public function notify() {
		foreach ($this->observers as $observer) {
			$observer->update($this);
		}
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
		$this->setEvent($event);
		$this->setArgs($args);

		$this->notify();
	}

	/**
	 * method to get the arguments of the object
	 * 
	 * @return array the arguments of the object	
	 */
	protected function getArgs() {
		return $this->args;
	}

	/**
	 * method to set the arguments of the object
	 * 
	 * @param array $args arguments to set the object
	 * 
	 * @return void
	 */
	protected function setArgs(array $args) {
		return $this->args = $args;
	}

	/**
	 * method to get the event of the object
	 * 
	 * @return string the event of the object	
	 */
	protected function getEvent() {
		return $this->event;
	}

	/**
	 * method to set the event of the object
	 * 
	 * @param string $event event to set the object
	 * 
	 * @return void
	 */
	protected function setEvent($event) {
		return $this->event = $event;
	}

}

