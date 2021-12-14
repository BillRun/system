<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Basic event notifier
 *
 * @since 5.6
 */
abstract class Billrun_Events_Notifiers_Base {

	/**
	 * saves general settings for notifier
	 */
	protected $settings = array();

	/**
	 * saves current event
	 */
	protected $event = array();

	/**
	 * additional parameters
	 */
	protected $params = array();

	public function __construct($event, $params = array()) {
		$this->event = $event;
		$this->params = $params;
		$this->settings = $this->getSettings();
	}

	/**
	 * gets the relevant settings for the notifier
	 * 
	 * @return type
	 */
	protected function getSettings() {
		return array_merge(
				Billrun_Factory::config()->getConfigValue('events.settings', array()),
				Billrun_Factory::config()->getConfigValue('events.settings.' . $this->getNotifierName(), array())
		);
	}

	/**
	 * Notify abstract function
	 * 
	 * @return mixed- response from notifier on success, false on failure
	 */
	abstract public function notify();

	/**
	 * gets notifier name
	 */
	abstract public function getNotifierName();
}
