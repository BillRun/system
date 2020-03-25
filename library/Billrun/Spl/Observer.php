<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Spl Observer
 *
 * @package SPL
 * @since    0.5
 */
abstract class Billrun_Spl_Observer implements SplObserver {

	/**
	 * Method to trigger events.
	 * The method first generates the even from the argument array. Then it unsets the argument
	 * since the argument has no bearing on the event handler.
	 * If the method exists it is called and returns its return value. If it does not exist it
	 * returns null.
	 *
	 * @param   array  &$args  Arguments
	 *
	 * @return  mixed  Routine return value
	 */
	public function update(SplSubject $subject) {

		// get the event and args from the subject (dispatcher) to use in the plugin
		$event = $subject->getEvent();
		$args = $subject->getArgs();

		/*
		 * If the method to handle an event exists, call it and return its value
		 * If it does not exist, return null.
		 */
		if (method_exists($this, $event) && $this->isEnabled()) {
			return call_user_func_array(array($this, $event), $args);
		} else {
			return null;
		}
	}

}
