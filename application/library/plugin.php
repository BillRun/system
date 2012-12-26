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
abstract class plugin extends spl_object {

	/**
	 * Event object to observe.
	 *
	 * @var mixed
	 */
	protected $_subject = null;

	/**
	 * parameters for the plugin
	 *
	 * @var array
	 */
	public $params = null;

	/**
	 * The name of the plugin
	 *
	 * @var string
	 */
	protected $_name = null;

	/**
	 * The plugin type
	 *
	 * @var    string
	 */
	protected $_type = null;

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
		// Get the parameters.
		if (isset($config['params'])) {
			if ($config['params'] instanceof JRegistry) {
				$this->params = $config['params'];
			} else {
				$this->params = new JRegistry;
				$this->params->loadString($config['params']);
			}
		}

		// Get the plugin name.
		if (isset($config['name'])) {
			$this->_name = $config['name'];
		}

		// Get the plugin type.
		if (isset($config['type'])) {
			$this->_type = $config['type'];
		}

		// Register the observer ($this) so we can be notified
		$subject->attach($this);

		// Set the subject to observe
		$this->_subject = &$subject;
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
	public function update($args) {
		// First let's get the event from the argument array.  Next we will unset the
		// event argument as it has no bearing on the method to handle the event.
		$event = $args['event'];
		unset($args['event']);

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