<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing dispatcher class
 *
 * @package  Dispatcher
 * @since    1.0
 */
interface Billrun_Dispatcher {

	/**
	 * Registers an event handler to the event dispatcher
	 *
	 * @param   string  $event    Name of the event to register handler for
	 * @param   string  $handler  Name of the event handler
	 *
	 * @return  void
	 *
	 */
	public function registerEvent($event);
	
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
	public function trigger($event);

	/**
	 * Attach an observer object
	 *
	 * @param   object  $observer  An observer object to attach
	 *
	 * @return  void
	 *
	 * @since   11.3
	 */
	public function attach($observer);
	
	/**
	 * Detach an observer object
	 *
	 * @param   object  $observer  An observer object to detach.
	 *
	 * @return  boolean  True if the observer object was detached.
	 *
	 * @since   11.3
	 */
	public function detach($observer);


}