<?php
/**
 * This is API documentation for PhpStorm
 */

final class Yaf_Application
{
	protected $config;
	protected $dispatcher;
	protected static $_app;
	protected $_modules;
	protected $_running;
	protected $_environ;

	/**
	 * @return Yaf_Application|null
	 */
	public static function app() {}

	/**
	 * @param Yaf_Bootstrap_Abstract $bootstrap
	 * @return Yaf_Application
	 */
	public function bootstrap(Yaf_Bootstrap_Abstract $bootstrap = null) {}

	/**
	 * @return Yaf_Application
	 */
	public function clearLastError() {}

	/**
	 * @return void
	 */
	private function __clone() {}

	/**
	 * @param mixed  $config
	 * @param string $envrion
	 */
	public function __construct($config, $envrion = null) {}

	/**
	 * @return void
	 */
	public function __destruct() {}

	/**
	 * @return string
	 */
	public function environ() {}

	/**
	 * @param callable $entry,...
	 * @return void
	 */
	public function execute(callable $entry) {}

	/**
	 * @return Yaf_Application
	 */
	public function getAppDirectory() {}

	/**
	 * @return Yaf_Config_Abstract
	 */
	public function getConfig() {}

	/**
	 * @return Yaf_Dispatcher
	 */
	public function getDispatcher() {}

	/**
	 * @return string
	 */
	public function getLastErrorMsg() {}

	/**
	 * @return int
	 */
	public function getLastErrorNo() {}

	/**
	 * @return array
	 */
	public function getModules() {}

	/**
	 * @return void
	 */
	public function run() {}

	/**
	 * @param string $directory
	 * @return Yaf_Application
	 */
	public function setAppDirectory($directory) {}

	/**
	 * @return void
	 */
	private function __sleep() {}

	/**
	 * @return void
	 */
	private function __wakeup() {}
}

abstract class Yaf_Bootstrap_Abstract {}

final class Yaf_Dispatcher
{
	protected $_router;
	protected $_view;
	protected $_request;
	protected $_plugins;
	protected static $_instance;
	protected $_auto_render;
	protected $_return_response;
	protected $_instantly_flush;
	protected $_default_module;
	protected $_default_controller;
	protected $_default_action;

	/**
	 * @param bool $flag
	 * @return Yaf_Dispatcher
	 */
	public function autoRender($flag) {}

	/**
	 * @param bool $flag
	 * @return Yaf_Dispatcher
	 */
	public function catchException($flag) {}

	/**
	 * @return void
	 */
	private function __clone() {}

	/**
	 *
	 */
	public function __construct() {}

	/**
	 * @return Yaf_Dispatcher
	 */
	public function disableView() {}

	/**
	 * @param Yaf_Request_Abstract $request
	 * @return Yaf_Response_Abstract
	 */
	public function dispatch(Yaf_Request_Abstract $request) {}

	/**
	 * @return Yaf_Dispatcher
	 */
	public function enableView() {}

	/**
	 * @param bool $flag
	 * @return Yaf_Dispatcher
	 */
	public function flushInstantly($flag) {}

	/**
	 * @return Yaf_Application
	 */
	public function getApplication() {}

	/**
	 * @return Yaf_Dispatcher
	 */
	public static function getInstance() {}

	/**
	 * @return Yaf_Request_Abstract
	 */
	public function getRequest() {}

	/**
	 * @return Yaf_Router
	 */
	public function getRouter() {}

	/**
	 * @param string $templates_dir
	 * @param array  $options
	 * @return Yaf_View_Interface
	 */
	public function initView($templates_dir, array $options = null) {}

	/**
	 * @param Yaf_Plugin_Abstract $plugin
	 * @return Yaf_Dispatcher
	 */
	public function registerPlugin(Yaf_Plugin_Abstract $plugin) {}

	/**
	 * @param bool $flag
	 * @return Yaf_Dispatcher
	 */
	public function returnResponse($flag) {}

	/**
	 * @param string $action
	 * @return Yaf_Dispatcher
	 */
	public function setDefaultAction($action) {}

	/**
	 * @param string $controller
	 * @return Yaf_Dispatcher
	 */
	public function setDefaultController($controller) {}

	/**
	 * @param string $module
	 * @return Yaf_Dispatcher
	 */
	public function setDefaultModule($module) {}

	/**
	 * @param callable $callback
	 * @param int      $error_types
	 * @return Yaf_Dispatcher
	 */
	public function setErrorHandler(callable $callback, $error_types) {}

	/**
	 * @param Yaf_Request_Abstract $request
	 * @return Yaf_Dispatcher
	 */
	public function setRequest(Yaf_Request_Abstract $request) {}

	/**
	 * @param Yaf_View_Interface $view
	 * @return Yaf_Dispatcher
	 */
	public function setView(Yaf_View_Interface $view) {}

	/**
	 * @return void
	 */
	private function __sleep() {}

	/**
	 * @param bool $flag
	 * @return Yaf_Dispatcher
	 */
	public function throwException($flag) {}

	/**
	 * @return void
	 */
	private function __wakeup() {}
}

abstract class Yaf_Config_Abstract
{
	protected $_config;
	protected $_readonly;

	/**
	 * @param string $name
	 * @param mixed  $value
	 * @return mixed
	 */
	public function get($name, $value) {}

	/**
	 * @return bool
	 */
	public function readonly() {}

	/**
	 * @return Yaf_Config_Abstract
	 */
	public function set() {}

	/**
	 * @return array
	 */
	public function toArray() {}
}

class Yaf_Config_Ini extends Yaf_Config_Abstract implements Iterator, Traversable, ArrayAccess, Countable
{
	/**
	 * @param string $config_file
	 * @param string $section
	 */
	public function __construct($config_file, $section = null) {}

	/**
	 * @return void
	 */
	public function count() {}

	/**
	 * @return void
	 */
	public function current() {}

	/**
	 * @param string $name
	 * @return mixed
	 */
	public function __get($name) {}

	/**
	 * @param string $name
	 * @return void
	 */
	public function __isset($name) {}

	/**
	 * @return void
	 */
	public function key() {}

	/**
	 * @return void
	 */
	public function next() {}

	/**
	 * @param string $name
	 * @return void
	 */
	public function offsetExists($name) {}

	/**
	 * @param string $name
	 * @return mixed
	 */
	public function offsetGet($name) {}

	/**
	 * @param string $name
	 * @param string $value
	 * @return void
	 */
	public function offsetSet($name, $value) {}

	/**
	 * @param string $name
	 * @return void
	 */
	public function offsetUnset($name) {}

	/**
	 * @return void
	 */
	public function readonly() {}

	/**
	 * @return void
	 */
	public function rewind() {}

	/**
	 * @param string $name
	 * @param mixed  $value
	 * @return void
	 */
	public function __set($name, $value) {}

	/**
	 * @return void
	 */
	public function toArray() {}

	/**
	 * @return void
	 */
	public function valid() {}
}

class Yaf_Config_Simple extends Yaf_Config_Abstract implements Iterator, Traversable, ArrayAccess, Countable
{
	protected $_readonly;

	/**
	 * @param string $config_file
	 * @param string $section
	 */
	public function __construct($config_file, $section = null) {}

	/**
	 * @return void
	 */
	public function count() {}

	/**
	 * @return void
	 */
	public function current() {}

	/**
	 * @param string $name
	 * @return mixed
	 */
	public function __get($name) {}

	/**
	 * @param string $name
	 * @return void
	 */
	public function __isset($name) {}

	/**
	 * @return void
	 */
	public function key() {}

	/**
	 * @return void
	 */
	public function next() {}

	/**
	 * @param string $name
	 * @return void
	 */
	public function offsetExists($name) {}

	/**
	 * @param string $name
	 * @return mixed
	 */
	public function offsetGet($name) {}

	/**
	 * @param string $name
	 * @param string $value
	 * @return void
	 */
	public function offsetSet($name, $value) {}

	/**
	 * @param string $name
	 * @return void
	 */
	public function offsetUnset($name) {}

	/**
	 * @return void
	 */
	public function readonly() {}

	/**
	 * @return void
	 */
	public function rewind() {}

	/**
	 * @param string $name
	 * @param string $value
	 * @return void
	 */
	public function __set($name, $value) {}

	/**
	 * @return void
	 */
	public function toArray() {}

	/**
	 * @return void
	 */
	public function valid() {}
}

abstract class Yaf_Controller_Abstract
{
	public $actions;
	protected $_module;
	protected $_name;
	protected $_request;
	protected $_response;
	protected $_invoke_args;
	protected $_view;

	/**
	 * @return void
	 */
	final private function __clone() {}

	final private function __construct() {}

	/**
	 * @param string $tpl
	 * @param array  $parameters
	 * @return void
	 */
	final protected function display($tpl, array $parameters = null) {}

	/**
	 * @param string $module
	 * @param string $controller
	 * @param string $action
	 * @param array  $parameters
	 * @return void
	 */
	final public function forward($module, $controller = null, $action = null, $parameters = null) {}

	/**
	 * @param string $name
	 * @return mixed
	 */
	final public function getInvokeArg($name) {}

	/**
	 * @return mixed
	 */
	final public function getInvokeArgs() {}

	/**
	 * @return mixed
	 */
	final public function getModuleName() {}

	/**
	 * @return Yaf_Request_Abstract
	 */
	final public function getRequest() {}

	/**
	 * @return Yaf_Response_Abstract
	 */
	final public function getResponse() {}

	/**
	 * @return mixed
	 */
	final public function getView() {}

	/**
	 * @return mixed
	 */
	final public function getViewpath() {}

	/**
	 * @param array $options
	 * @return void
	 */
	final public function initView(array $options) {}

	/**
	 * @param string $url
	 * @return void
	 */
	final public function redirect($url) {}

	/**
	 * @param string $tpl
	 * @param array  $parameters
	 * @return void
	 */
	final protected function render($tpl, array $parameters = null) {}

	/**
	 * @param string $view_directory
	 * @return void
	 */
	final public function setViewpath($view_directory) {}
}

abstract class Yaf_Action_Abstract extends Yaf_Controller_Abstract
{
	protected $_controller;

	/**
	 * @param mixed $arg,...
	 * @return mixed
	 */
	abstract public function execute($arg = null);

	/**
	 * @return Yaf_Controller_Abstract
	 */
	public function getController() {}
}

interface Yaf_View_Interface
{
	/**
	 * @param string $name
	 * @param string $value
	 * @return bool
	 */
	public function assign($name, $value = null);

	/**
	 * @param string $tpl
	 * @param array  $tpl_vars
	 * @return bool
	 */
	public function display($tpl, array $tpl_vars = null);

	/**
	 * @return mixed
	 */
	public function getScriptPath();

	/**
	 * @param string $tpl
	 * @param array  $tpl_vars
	 * @return string
	 */
	public function render($tpl, array $tpl_vars = null);

	/**
	 * @param string $template_dir
	 * @return void
	 */
	public function setScriptPath($template_dir);
}

class Yaf_View_Simple implements Yaf_View_Interface
{
	protected $_tpl_vars;
	protected $_tpl_dir;

	/**
	 * @param string $name
	 * @param mixed  $value
	 * @return bool
	 */
	public function assign($name, $value = null) {}

	/**
	 * @param string $name
	 * @param mixed  $value
	 * @return bool
	 */
	public function assignRef($name, &$value) {}

	/**
	 * @param string $name
	 * @return bool
	 */
	public function clear($name) {}

	/**
	 * @param string $tempalte_dir
	 * @param array  $options
	 */
	public function __construct($tempalte_dir, array $options = null) {}

	/**
	 * @param string $tpl
	 * @param array  $tpl_vars
	 * @return bool
	 */
	public function display($tpl, array $tpl_vars = null) {}

	/**
	 * @param string $name
	 * @return mixed
	 */
	public function __get($name) {}

	/**
	 * @return string
	 */
	public function getScriptPath() {}

	/**
	 * @param string $name
	 * @return void
	 */
	public function __isset($name) {}

	/**
	 * @param string $tpl
	 * @param array  $tpl_vars
	 * @return string
	 */
	public function render($tpl, array $tpl_vars = null) {}

	/**
	 * @param string $name
	 * @param mixed  $value
	 * @return void
	 */
	public function __set($name, $value) {}

	/**
	 * @param string $template_dir
	 * @return bool
	 */
	public function setScriptPath($template_dir) {}
}

class Yaf_Loader
{
	protected $_local_ns;
	protected $_library;
	protected $_global_library;
	static $_instance;

	/**
	 * @return void
	 */
	public function autoload() {}

	/**
	 * @return void
	 */
	public function clearLocalNamespace() {}

	/**
	 * @return void
	 */
	private function __clone() {}

	/**
	 *
	 */
	public function __construct() {}

	/**
	 * @return Yaf_Loader
	 */
	public static function getInstance() {}

	/**
	 * @param bool $is_global
	 * @return Yaf_Loader
	 */
	public function getLibraryPath($is_global = false) {}

	/**
	 * @return mixed
	 */
	public function getLocalNamespace() {}

	/**
	 * @return void
	 */
	public static function import() {}

	/**
	 * @return void
	 */
	public function isLocalName() {}

	/**
	 * @return void
	 */
	public function registerLocalNamespace() {}

	/**
	 * @param string $directory
	 * @param bool   $is_global
	 * @return Yaf_Loader
	 */
	public function setLibraryPath($directory, $is_global = false) {}

	/**
	 * @return void
	 */
	private function __sleep() {}

	/**
	 * @return void
	 */
	private function __wakeup() {}
}

class Yaf_Plugin_Abstract
{
	/**
	 * @param Yaf_Request_Abstract  $request
	 * @param Yaf_Response_Abstract $response
	 * @return void
	 */
	public function dispatchLoopShutdown(Yaf_Request_Abstract $request, Yaf_Response_Abstract $response) {}

	/**
	 * @param Yaf_Request_Abstract  $request
	 * @param Yaf_Response_Abstract $response
	 * @return void
	 */
	public function dispatchLoopStartup(Yaf_Request_Abstract $request, Yaf_Response_Abstract $response) {}

	/**
	 * @param Yaf_Request_Abstract  $request
	 * @param Yaf_Response_Abstract $response
	 * @return void
	 */
	public function postDispatch(Yaf_Request_Abstract $request, Yaf_Response_Abstract $response) {}

	/**
	 * @param Yaf_Request_Abstract  $request
	 * @param Yaf_Response_Abstract $response
	 * @return void
	 */
	public function preDispatch(Yaf_Request_Abstract $request, Yaf_Response_Abstract $response) {}

	/**
	 * @param Yaf_Request_Abstract  $request
	 * @param Yaf_Response_Abstract $response
	 * @return void
	 */
	public function preResponse(Yaf_Request_Abstract $request, Yaf_Response_Abstract $response) {}

	/**
	 * @param Yaf_Request_Abstract  $request
	 * @param Yaf_Response_Abstract $response
	 * @return void
	 */
	public function routerShutdown(Yaf_Request_Abstract $request, Yaf_Response_Abstract $response) {}

	/**
	 * @param Yaf_Request_Abstract  $request
	 * @param Yaf_Response_Abstract $response
	 * @return void
	 */
	public function routerStartup(Yaf_Request_Abstract $request, Yaf_Response_Abstract $response) {}
}

class Yaf_Registry
{
	static $_instance;
	protected $_entries;

	/**
	 * @return void
	 */
	private function __clone() {}

	/**
	 *
	 */
	public function __construct() {}

	/**
	 * @param string $name
	 * @return void
	 */
	public static function del($name) {}

	/**
	 * @param string $name
	 * @return mixed
	 */
	public static function get($name) {}

	/**
	 * @param string $name
	 * @return void
	 */
	public static function has($name) {}

	/**
	 * @param string $name
	 * @param string $value
	 * @return void
	 */
	public static function set($name, $value) {}
}

class Yaf_Request_Abstract
{
	/** string */
	const SCHEME_HTTP = 'http';
	/** string */
	const SCHEME_HTTPS = 'https';

	public $module;
	public $controller;
	public $action;
	public $method;
	protected $params;
	protected $language;
	protected $_exception;
	protected $_base_uri;
	protected $uri;
	protected $dispatched;
	protected $routed;

	/**
	 * @return mixed
	 */
	public function getActionName() {}

	/**
	 * @return mixed
	 */
	public function getBaseUri() {}

	/**
	 * @return mixed
	 */
	public function getControllerName() {}

	/**
	 * @param string $name
	 * @param string $default
	 * @return mixed
	 */
	public function getEnv($name, $default = null) {}

	/**
	 * @return mixed
	 */
	public function getException() {}

	/**
	 * @return mixed
	 */
	public function getLanguage() {}

	/**
	 * @return mixed
	 */
	public function getMethod() {}

	/**
	 * @return mixed
	 */
	public function getModuleName() {}

	/**
	 * @param string $name
	 * @param string $default
	 * @return mixed
	 */
	public function getParam($name, $default = null) {}

	/**
	 * @return array
	 */
	public function getParams() {}

	/**
	 * @return mixed
	 */
	public function getRequestUri() {}

	/**
	 * @param string $name
	 * @param string $default
	 * @return mixed
	 */
	public function getServer($name, $default = null) {}

	/**
	 * @return mixed
	 */
	public function isCli() {}

	/**
	 * @return mixed
	 */
	public function isDispatched() {}

	/**
	 * @return mixed
	 */
	public function isGet() {}

	/**
	 * @return mixed
	 */
	public function isHead() {}

	/**
	 * @return mixed
	 */
	public function isOptions() {}

	/**
	 * @return mixed
	 */
	public function isPost() {}

	/**
	 * @return mixed
	 */
	public function isPut() {}

	/**
	 * @return mixed
	 */
	public function isRouted() {}

	/**
	 * @return mixed
	 */
	public function isXmlHttpRequest() {}

	/**
	 * @param string $action
	 * @return void
	 */
	public function setActionName($action) {}

	/**
	 * @param string $uri
	 * @return void
	 */
	public function setBaseUri($uri) {}

	/**
	 * @param string $controller
	 * @return void
	 */
	public function setControllerName($controller) {}

	/**
	 * @return void
	 */
	public function setDispatched() {}

	/**
	 * @param string $module
	 * @return void
	 */
	public function setModuleName($module) {}

	/**
	 * @param string $name
	 * @param string $value
	 * @return void
	 */
	public function setParam($name, $value = null) {}

	/**
	 * @param string $uri
	 * @return void
	 */
	public function setRequestUri($uri) {}

	/**
	 * @param string $flag
	 * @return void
	 */
	public function setRouted($flag) {}
}

class Yaf_Request_Http extends Yaf_Request_Abstract
{
	/**
	 * @return void
	 */
	private function __clone() {}

	/**
	 *
	 */
	public function __construct() {}

	/**
	 * @return mixed
	 */
	public function get() {}

	/**
	 * @return mixed
	 */
	public function getCookie() {}

	/**
	 * @return mixed
	 */
	public function getFiles() {}

	/**
	 * @return mixed
	 */
	public function getPost() {}

	/**
	 * @return mixed
	 */
	public function getQuery() {}

	/**
	 * @return mixed
	 */
	public function getRequest() {}

	/**
	 * @return mixed
	 */
	public function isXmlHttpRequest() {}
}

class Yaf_Request_Simple extends Yaf_Request_Abstract
{
	/** string */
	const SCHEME_HTTP = 'http';
	/** string */
	const SCHEME_HTTPS = 'https';

	/**
	 * @return void
	 */
	private function __clone() {}

	/**
	 *
	 */
	public function __construct() {}

	/**
	 * @return void
	 */
	public function get() {}

	/**
	 * @return void
	 */
	public function getCookie() {}

	/**
	 * @return void
	 */
	public function getFiles() {}

	/**
	 * @return void
	 */
	public function getPost() {}

	/**
	 * @return void
	 */
	public function getQuery() {}

	/**
	 * @return void
	 */
	public function getRequest() {}

	/**
	 * @return void
	 */
	public function isXmlHttpRequest() {}
}

class Yaf_Response_Abstract
{
	protected $_header;
	protected $_body;
	protected $_sendheader;

	/**
	 * @return void
	 */
	public function appendBody() {}

	/**
	 * @return void
	 */
	public function clearBody() {}

	/**
	 * @return void
	 */
	public function clearHeaders() {}

	/**
	 * @return void
	 */
	private function __clone() {}

	/**
	 *
	 */
	public function __construct() {}

	/**
	 * @return void
	 */
	public function __destruct() {}

	/**
	 * @return string
	 */
	public function getBody() {}

	/**
	 * @return mixed
	 */
	public function getHeader() {}

	/**
	 * @return mixed
	 */
	public function prependBody() {}

	/**
	 * @return mixed
	 */
	public function response() {}

	/**
	 * @return void
	 */
	protected function setAllHeaders() {}

	/**
	 * @return void
	 */
	public function setBody() {}

	/**
	 * @return void
	 */
	public function setHeader() {}

	/**
	 * @return void
	 */
	public function setRedirect() {}

	/**
	 * @return void
	 */
	private function __toString() {}
}

interface Yaf_Route_Interface
{
	/**
	 * @param Yaf_Request_Abstract $request
	 * @return bool
	 */
	public function route(Yaf_Request_Abstract $request);
}

class Yaf_Route_Map implements Yaf_Route_Interface
{
	protected $_ctl_router;
	protected $_delimeter;

	/**
	 * @param bool|string $controller_prefer
	 * @param string      $delimiter
	 */
	public function __construct($controller_prefer = false, $delimiter = '') {}

	/**
	 * @param Yaf_Request_Abstract $request
	 * @return bool
	 */
	public function route(Yaf_Request_Abstract $request) {}
}

class Yaf_Route_Regex implements Yaf_Route_Interface
{
	protected $_route;
	protected $_default;
	protected $_maps;
	protected $_verify;

	/**
	 * @param string $match
	 * @param array  $route
	 * @param array  $map
	 * @param array  $verify
	 */
	public function __construct($match, array $route, array $map = null, array $verify = null) {}

	/**
	 * @param Yaf_Request_Abstract $request
	 * @return bool
	 */
	public function route(Yaf_Request_Abstract $request) {}
}

class Yaf_Route_Rewrite implements Yaf_Route_Interface
{
	protected $_route;
	protected $_default;
	protected $_verify;

	/**
	 * @param string $match
	 * @param array  $route
	 * @param array  $verify
	 */
	public function __construct($match, array $route, array $verify = null) {}

	/**
	 * @param Yaf_Request_Abstract $request
	 * @return bool
	 */
	public function route(Yaf_Request_Abstract $request) {}
}

class Yaf_Router
{
	protected $_routes;
	protected $_current;

	/**
	 * @param Yaf_Config_Abstract $config
	 * @return void
	 */
	public function addConfig(Yaf_Config_Abstract $config) {}

	/**
	 * @param string              $name
	 * @param Yaf_Route_Interface $route
	 * @return Yaf_Router
	 */
	public function addRoute($name, Yaf_Route_Interface $route) {}

	public function __construct() {}

	/**
	 * @return string
	 */
	public function getCurrentRoute() {}

	/**
	 * @param string $name
	 * @return mixed
	 */
	public function getRoute($name) {}

	/**
	 * @return mixed
	 */
	public function getRoutes() {}

	/**
	 * @param Yaf_Request_Abstract $request
	 * @return bool
	 */
	public function route(Yaf_Request_Abstract $request) {}
}

class Yaf_Route_Simple implements Yaf_Route_Interface
{
	protected $controller;
	protected $module;
	protected $action;

	/**
	 * @param string $module_name
	 * @param string $controller_name
	 * @param string $action_name
	 */
	public function __construct($module_name, $controller_name, $action_name) {}

	/**
	 * @param Yaf_Request_Abstract $request
	 * @return bool
	 */
	public function route(Yaf_Request_Abstract $request) {}
}

class Yaf_Route_Static extends Yaf_Router
{
	/**
	 * @param string $uri
	 * @return void
	 */
	public function match($uri) {}

	/**
	 * @param Yaf_Request_Abstract $request
	 * @return bool
	 */
	public function route(Yaf_Request_Abstract $request) {}
}

class Yaf_Route_Supervar implements Yaf_Route_Interface
{
	protected $_var_name;

	/**
	 * @param string $supervar_name
	 */
	public function __construct($supervar_name) {}

	/**
	 * @param Yaf_Request_Abstract $request
	 * @return bool
	 */
	public function route(Yaf_Request_Abstract $request) {}
}

class Yaf_Session implements Iterator, Traversable, ArrayAccess, Countable
{
	protected static $_instance;
	protected $_session;
	protected $_started;

	/**
	 * @return void
	 */
	private function __clone() {}

	/**
	 *
	 */
	public function __construct() {}

	/**
	 * @return void
	 */
	public function count() {}

	/**
	 * @return void
	 */
	public function current() {}

	/**
	 * @param string $name
	 * @return void
	 */
	public function del($name) {}

	/**
	 * @param string $name
	 * @return mixed
	 */
	public function __get($name) {}

	/**
	 * @return mixed
	 */
	public static function getInstance() {}

	/**
	 * @param string $name
	 * @return void
	 */
	public function has($name) {}

	/**
	 * @param string $name
	 * @return void
	 */
	public function __isset($name) {}

	/**
	 * @return void
	 */
	public function key() {}

	/**
	 * @return void
	 */
	public function next() {}

	/**
	 * @param string $name
	 * @return void
	 */
	public function offsetExists($name) {}

	/**
	 * @param string $name
	 * @return mixed
	 */
	public function offsetGet($name) {}

	/**
	 * @param string $name
	 * @param string $value
	 * @return void
	 */
	public function offsetSet($name, $value) {}

	/**
	 * @param string $name
	 * @return void
	 */
	public function offsetUnset($name) {}

	/**
	 * @return void
	 */
	public function rewind() {}

	/**
	 * @param string $name
	 * @param string $value
	 * @return void
	 */
	public function __set($name, $value) {}

	/**
	 * @return void
	 */
	private function __sleep() {}

	/**
	 * @return void
	 */
	public function start() {}

	/**
	 * @param string $name
	 * @return void
	 */
	public function __unset($name) {}

	/**
	 * @return void
	 */
	public function valid() {}

	/**
	 * @return void
	 */
	private function __wakeup() {}
}

class Yaf_Exception extends Exception {}
class Yaf_Exception_TypeError extends Yaf_Exception {}
class Yaf_Exception_StartupError extends Yaf_Exception {}
class Yaf_Exception_DispatchFailed extends Yaf_Exception {}
class Yaf_Exception_RouterFailed extends Yaf_Exception {}
class Yaf_Exception_LoadFailed extends Yaf_Exception {}
class Yaf_Exception_LoadFailed_Module extends Yaf_Exception_LoadFailed {}
class Yaf_Exception_LoadFailed_Controller extends Yaf_Exception_LoadFailed {}
class Yaf_Exception_LoadFailed_Action extends Yaf_Exception_LoadFailed {}
class Yaf_Exception_LoadFailed_View extends Yaf_Exception_LoadFailed {}