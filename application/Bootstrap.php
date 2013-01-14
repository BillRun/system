<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing bootstrap class
 *
 * @package  Bootstrap
 * @since    1.0
 */
class Bootstrap extends Yaf_Bootstrap_Abstract {

	public function _initPlugin(Yaf_Dispatcher $dispatcher) {

		// set include paths of the system.
		set_include_path(get_include_path() . PATH_SEPARATOR . Yaf_Loader::getInstance()->getLibraryPath());

		/* register a billrun plugin system from config */
		$dispatcher = Billrun_Dispatcher::getInstance();
		$config = Yaf_Application::app()->getConfig();

		if (isset($config->plugins)) {
			$plugins = $config->plugins->toArray();

			foreach ($plugins as $plugin) {
				Billrun_Log::getInstance()->log("Load plugin " . $plugin . PHP_EOL, Zend_log::DEBUG);
				$dispatcher->attach(new $plugin);
			}
		}
	}

}
