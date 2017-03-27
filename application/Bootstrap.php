<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing bootstrap class
 *
 * @package  Bootstrap
 * @since    0.5
 */
class Bootstrap extends Yaf_Bootstrap_Abstract {

	public function _initEnvironment(Yaf_Dispatcher $dispatcher) {
		if (!isset($_SERVER['HTTP_USER_AGENT'])) {
			Yaf_Application::app()->getDispatcher()->setDefaultController('Cli');
		}
	}
	
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

		// make the base action auto load (required by controllers actions)
		Yaf_Loader::getInstance(APPLICATION_PATH . '/application/helpers')->registerLocalNamespace('Action');
	}

	public function _initLayout(Yaf_Dispatcher $dispatcher) {
		// Enable template layout only on admin
		// TODO: make this more accurate
		if (strpos($dispatcher->getRequest()->getRequestUri(), "admin") !== FALSE && strpos($dispatcher->getRequest()->getRequestUri(), "edit") === FALSE && strpos($dispatcher->getRequest()->getRequestUri(), "confirm") === FALSE && strpos($dispatcher->getRequest()->getRequestUri(), "logDetails") === FALSE && strpos($dispatcher->getRequest()->getRequestUri(), "wholesaleajax") === FALSE) {
			$path = Billrun_Factory::config()->getConfigValue('application.directory');
			$view = new Yaf_View_Simple($path . '/views/layout');
			$dispatcher->setView($view);
		}
	}

}
