<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Spl Object
 *
 * @package  SPL
 * @since    0.5
 */
class Billrun_Spl_Subject implements SplSubject {

	/**
	 * the observers that can fire events
	 * 
	 * @var array
	 */
	protected $observers = array();

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

	public function getImplementors($methodName) {
		$plugins = [];
		foreach ($this->observers as $observer) {
			if(method_exists($observer, $methodName)) {
				$plugins[] = $observer->getName();
			}
		}
		return $plugins;
	}
}
