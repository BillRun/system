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
 * @since    0.5
 */
class Bootstrap extends Yaf_Bootstrap_Abstract {

	public function _initPlugin(Yaf_Dispatcher $dispatcher) {

		// set include paths of the system.
		set_include_path(get_include_path() . PATH_SEPARATOR . Yaf_Loader::getInstance()->getLibraryPath());

		/* register a billrun plugin system from config */
		$config = Yaf_Application::app()->getConfig();

		if (isset($config->plugins)) {
			$plugins = $config->plugins->toArray();
			$dispatcher = Billrun_Dispatcher::getInstance();

			foreach ($plugins as $plugin) {
				Billrun_Log::getInstance()->log("Load plugin " . $plugin, Zend_log::DEBUG);
				$dispatcher->attach(new $plugin);
			}
		}
		
		if (isset($config->chains)) {
			$chains = $config->chains->toArray();
			$dispatcherChain = Billrun_Dispatcher::getInstance(array('type' => 'chain'));

			foreach ($chains as $chain) {
				Billrun_Log::getInstance()->log("Load plugin " . $chain, Zend_log::DEBUG);
				$dispatcherChain->attach(new $chain);
			}
		}

		// make the helpers autoload
		$loader = Yaf_Loader::getInstance(APPLICATION_PATH . '/application/helpers');
		if (isset($config->namespaces)) {
			$namespaces = $config->namespaces->toArray();
			foreach ($namespaces as $namespace) {
				$loader->registerLocalNamespace($namespace);
			}
		}


	}

}
