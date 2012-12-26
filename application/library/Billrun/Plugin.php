<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing abstract plugin class
 *
 * @package  plugin
 * @since    1.0
 */
abstract class Billrun_Plugin extends Billrun_Spl_Object {

	/**
	 * plugin name
	 * 
	 * @var string 
	 */
	protected $name = 'Billrun';

	/**
	 * Constructor
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array   $config    An optional associative array of configuration settings.
	 *                             Recognized key values include 'name', 'group', 'params', 'language'
	 *                             (this list is not meant to be comprehensive).
	 *
	 */
	public function __construct($subject, $config = array()) {

		// Register the observer ($this) so we can be notified
		$subject->attach($this);

		// Set the subject to observe
		$this->_subject = &$subject;
	}

	/**
	 * method to receive plugin name
	 * this will be used by the subject (dispatcher)
	 * 
	 * @return String The plugin name
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * method to set the plugin name
	 * 
	 * @param string $name The plugin name
	 */
	public function setName($name) {
		$this->name = $name;
	}

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

		$event = $subject->getEvent();
		$args = $subject->getArgs();

		/*
		 * If the method to handle an event exists, call it and return its return
		 * value.  If it does not exist, return null.
		 */
		if (method_exists($this, $event)) {
			return call_user_func_array(array($this, $event), $args);
		} else {
			return null;
		}
	}

}