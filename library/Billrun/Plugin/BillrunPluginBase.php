<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing abstract plugin class
 *
 * @package  plugin
 * @since    0.5
 */
abstract class Billrun_Plugin_BillrunPluginBase extends Billrun_Spl_Observer {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'billrun';

	/**
	 * plugin options
	 *
	 * @var array
	 */
	protected $options = [];

	/**
	 * is the plugin enabled
	 *
	 * @var boolean
	 */
	protected $enabled = true;

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
	 * method to receive plugin options
	 * this will be used by the subject (dispatcher)
	 *
	 * @return array of options
	 */
	public function getOptions() {
		return $this->options;
	}

	/**
	 * method to set the plugin options
	 *
	 * @param array of options
	 */
	public function setOptions($options) {
		$this->options = $options;
		$this->enabled = !empty($options['enabled']) ? $options['enabled'] : $this->enabled;
	}

	/**
	 * method to set the plugin "enable" flag
	 *
	 * @param boolean 
	 */
	public function setAvailability($enable) {
		$this->enabled = $enable;
	}

	/**
	 * whether the plugin is enabled 
	 *
	 * @param array of options
	 */
	public function isEnabled() {
		return $this->enabled;
	}

	/**
	 * Log a message at a priority the the main Billrun Log
	 *
	 * @param  string   $message   Message to log
	 * @param  integer  $priority  Priority of message
	 * @param  mixed    $extras    Extra information to log in event
	 * @return void
	 * @throws Zend_Log_Exception
	 */
	protected function log($message, $priority, $extras = null) {
		Billrun_Log::getInstance()->log($message, $priority, $extras);
	}

}
