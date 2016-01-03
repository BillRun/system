<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
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
		if ($this->isAdminLayout($dispatcher->getRequest()->getRequestUri())) {
			$path = Billrun_Factory::config()->getConfigValue('application.directory');
			$view = new Yaf_View_Simple($path . '/views/layout');
			$dispatcher->setView($view);
		}
	}
	
	/**
	 * Check if the URI requires an admin layout.
	 * @param string $requestUri - The received URI.
	 * @return true if URI is for adming layout.
	 */
	protected function isAdminLayout($requestUri) {
		$allowedInUriForAdming = array("admin");
		$bannedInUriForAdmin = array("edit", "confirm", "logDetails", "wholesaleajax");
		return $this->queryString($allowedInUriForAdming, $requestUri, TRUE) &&
			   $this->queryString($bannedInUriForAdmin, $requestUri, FALSE);
	}
	
	/**
	 * Search for an array of token strings in a string.
	 * @param array $array - Array of tokens.
	 * @param string - String to query.
	 * @param boolean $find - TRUE if all tokens should exist in the string, false
	 * if non should.
	 * @todo Instead of a foreach use a preg_match
	 * @todo This is very generic should move to a different module.
	 */
	protected function queryString($array, $string, $find) {
		foreach ($array as $token) {
			$notFound = (strpos($string, $token) === FALSE); 
			
			// If notfound is true, and find is false (should not be found) then the XOR result is true.
			// If notfound is false, and find is true (should be found) then the XOR result is true.
			// If notfound is true, and find is true (should be found) then the XOR result is false.
			if(!($notFound ^ $find)) {
				return false;
			}
		}
		
		return true;
	}

}
