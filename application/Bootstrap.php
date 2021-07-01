<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/** 
 * method to register namespace to path with backward compatibility to old yaf versions
 * 
 * @param string $namespace the namespace to register
 * @param string $path path attached to the namespace
 * 
 * @since version 5.14
 */
function br_yaf_register_autoload($namespace, $path) {
	if (version_compare(phpversion('yaf'), '3.2.0', '>=')) {
		$mapping = array(
			$namespace => $path . '/' . $namespace,
		);
		Yaf_Loader::getInstance()->registerNamespace($mapping);
	} else {
		Yaf_Loader::getInstance($path)->registerLocalNamespace($namespace);
	}
}

/**
 * Billing bootstrap class
 *
 * @package  Bootstrap
 * @since    0.5
 */
class Bootstrap extends Yaf_Bootstrap_Abstract {
	
    public function _initLoader(Yaf_Dispatcher $dispatcher) {
		// set composer vendor autoload
		Yaf_Loader::getInstance()->import(APPLICATION_PATH . '/vendor/autoload.php');
		// set include paths of the system.
		set_include_path(get_include_path() . PATH_SEPARATOR . Yaf_Loader::getInstance()->getLibraryPath()); // this is for Zend FW & Billrun objects
		// make the base action auto load (required by controllers actions)
		br_yaf_register_autoload('Action', APPLICATION_PATH . '/application/helpers');
	}

	public function _initEnvironment(Yaf_Dispatcher $dispatcher) {
		if (!isset($_SERVER['HTTP_USER_AGENT'])) {
			Yaf_Application::app()->getDispatcher()->setDefaultController('Cli');
		}
	}
	
	public function _initPlugin(Yaf_Dispatcher $dispatcher) {
		/* register a billrun plugin system from config */
		$config = Yaf_Application::app()->getConfig();
		$plugins = array();
		if (isset($config->plugins)) {
			$plugins = $config->plugins->toArray();
		}
		$definedPlugins = Billrun_Factory::config()->getConfigValue('plugins');
		if (isset($definedPlugins) && is_array($definedPlugins)) {
			$allPlugins = array_merge_recursive($definedPlugins, $plugins);
			$plugins = $this->handlePluginsConf($allPlugins);
		}
		if (!empty($plugins)) {
			$dispatcher = Billrun_Dispatcher::getInstance();

			foreach ($plugins as $plugin_name => $plugins_conf) {
				if (!empty($plugins_conf['configuration']['values'])) {
					$pluginObject = new $plugin_name($plugins_conf['configuration']['values']);
				} else {
					$pluginObject = new $plugin_name();
				}
				
				$dispatcher->attach($pluginObject);
				$pluginObject->setAvailability($plugins_conf['enabled']);
				if (isset($plugins_conf['configuration']['values'])) {
					$pluginObject->setOptions($plugins_conf['configuration']['values']);
				}
			}
		}

		if (isset($config->chains)) {
			$chains = $config->chains->toArray();
			$definedChains = Billrun_Factory::config()->getConfigValue('chains');
			if (isset($definedChains) && is_array($definedChains)) {
				$allChains = array_merge($definedChains, $chains);
				$chains = array_unique($allChains);
			}
			$dispatcherChain = Billrun_Dispatcher::getInstance(array('type' => 'chain'));

			foreach ($chains as $chain) {
				$dispatcherChain->attach(new $chain);
			}
		}
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
			if (!($notFound ^ $find)) {
				return false;
			}
		}

		return true;
	}

	public function _initRoutes() {
		$match = "#^/billapi/(\w+)/(\w+)/?(\w*)#";
		$route = array(
			'module' => 'billapi',
			'controller' => ':action',
		);
		$map = array(
			1 => "collection",
			2 => "action",
			3 => "id",
		);
		$routeRegex = new Yaf_Route_Regex($match, $route, $map);
		Yaf_Dispatcher::getInstance()->getRouter()->addRoute("billapi", $routeRegex);
		
		// add API versions backward compatibility
		$match = "#^/api/v/(\w+)/(\w+)#";
		$route = array(
			'controller' => 'api',
			'action' => 'versionsbc',
		);
		$map = array(
			1 => "api_version",
			2 => "api_action",
		);
		$routeRegex = new Yaf_Route_Regex($match, $route, $map);
		Yaf_Dispatcher::getInstance()->getRouter()->addRoute("versions_bc", $routeRegex);
		
		$match = "#^/plugins/(\w+)/(\w+)/?(\w*)#";
		$route = array(
			'controller' => 'plugins',
			'action' => 'index',
		);
		$map = array(
			1 => "plugin",
			2 => "action",
			3 => "id",
		);
		$routeRegex = new Yaf_Route_Regex($match, $route, $map);
		Yaf_Dispatcher::getInstance()->getRouter()->addRoute("plugins", $routeRegex);
	}
	
	/**
	 * Rearrange all the plugins from db and ini - to be in the latest plugins structure, and prevent duplications.
	 * @param array $plugins
	 * @return array.
	 */
	public function	handlePluginsConf($plugins) {
		$plugins_list = [];
		foreach ($plugins as $key => $plugin) {
			$pluginName = is_array($plugin) ? $plugin['name'] : $plugin;
			if (!isset($plugins_list[$pluginName])) {
				if (is_array($plugin)) {
					$pluginName = $plugin['name'];
					if (in_array($plugin['name'], $plugins)) {
						array_splice($plugins, array_search($plugin['name'], $plugins), 1);
					}
					$plugins_list[$pluginName] = $plugin;
				} else {
					$pluginName = $plugin;
					$hideFromUI = ($pluginName == 'calcCpuPlugin') ? false : true;
					$system = in_array($pluginName, ['calcCpuPlugin', 'csiPlugin', 'autorenewPlugin', 'fraudPlugin']) ? true : false;
					$plugins_list[$pluginName] = ['name' => $pluginName, 'enabled' => true, 'system' => $system, 'hide_from_ui' => $hideFromUI];
				}
			}
		}
		return $plugins_list;
	}
}
