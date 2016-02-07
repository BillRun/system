<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
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
