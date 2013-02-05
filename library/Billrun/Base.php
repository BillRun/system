<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing basic abstract class
 *
 * @package  base
 * @since    1.0
 */
abstract class Billrun_Base {

	/**
	 * the database we are working on
	 *
	 * @var db resource
	 */
	protected $db = null;

	/**
	 * the stamp of the aggregator
	 * used for mark the aggregation
	 *
	 * @var db resource
	 */
	protected $stamp = null;

	/**
	 * the log of the system
	 *
	 * @var Billrun_Log
	 */
	protected $log;

	/**
	 * the configuration of the system
	 *
	 * @var YAF_Config
	 */
	protected $config;

	/**
	 * dispatcher of the plugin system
	 *
	 * @var dispatcher class
	 */
	protected $dispatcher;

	/**
	 * chain dispatcher of the plugin system
	 *
	 * @var dispatcher class
	 */
	protected $chain;

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'base';

	/**
	 * constant of log collection name
	 */

	const log_table = 'log';

	/**
	 * constant of lines collection name
	 */
	const lines_table = 'lines';

	/**
	 * constant of billrun collection name
	 */
	const billrun_table = 'billrun';

	/**
	 * constant of events collection name
	 */
	const events_table = 'events';

	/**
	 * constant for base date format
	 */
	const base_dateformat = 'Y-m-d h:i:s';

	/**
	 * constructor
	 * 
	 * @param array $options
	 * @todo use factory for all basic instances (config, log, db, etc)
	 */
	public function __construct($options = array()) {
		if (isset($options['config'])) {
			$this->config = $options['config'];
		} else {
			$this->config = Yaf_Application::app()->getConfig();
		}

		if (isset($options['log'])) {
			$this->log = $options['log'];
		} else {
			$this->log = Billrun_Log::getInstance();
		}

		if (isset($options['db'])) {
			$this->setDB($options['db']);
		} else {
			$conn = Mongodloid_Connection::getInstance($this->config->db->host, $this->config->db->port);
			$this->setDB($conn->getDB($this->config->db->name));
		}

		if (isset($options['stamp']) && $options['stamp']) {
			$this->setStamp($options['stamp']);
		} else {
			$this->setStamp(uniqid(get_class($this)));
		}

		if (isset($options['dispatcher'])) {
			$this->dispatcher = $options['dispatcher'];
		} else {
			$this->dispatcher = Billrun_Dispatcher::getInstance();
		}

		if (isset($options['chain'])) {
			$this->chain = $options['chain'];
		} else {
			$this->chain = Billrun_Dispatcher::getInstance(array('type' => 'chain'));
		}

		if (isset($options['type'])) {
			static::$type = $options['type'];
		}
	}

	/**
	 * set database of the basic object
	 *
	 * @param resource $db the database instance to set
	 *
	 * @return mixed self instance
	 */
	public function setDB($db) {
		$this->db = $db;
		return $this;
	}

	/**
	 * set stamp of the basic object
	 * used for unique object actions
	 *
	 * @param string $stamp the stamp to set
	 *
	 * @return mixed self instance
	 */
	public function setStamp($stamp) {
		$this->stamp = $stamp;
		return $this;
	}

	/**
	 * get stamp of the basic object
	 * used for unique object actions
	 *
	 * @return string the stamp of the object
	 */
	public function getStamp() {
		return $this->stamp;
	}

	/**
	 * Loose coupling of objects in the system
	 *
	 * @return mixed the bridge class
	 */
	static public function getInstance() {
		$args = func_get_args();
		if (!is_array($args)) {
			$type = $args['type'];
			$args = array();
		} else {
			$type = $args[0]['type'];
			unset($args[0]['type']);
			$args = $args[0];
		}

		$config_type = Yaf_Application::app()->getConfig()->{$type};
		$called_class = get_called_class();

		if ($config_type &&
			isset($config_type->{$called_class::$type}) &&
			isset($config_type->{$called_class::$type}->type)) {
			$class_type = $config_type[$called_class::$type]['type'];
			$args = array_merge($args, $config_type->toArray());
			$args['type'] = $type;
		} else {
			$class_type = $type;
		}

		$class = $called_class . '_' . ucfirst($class_type);
		return new $class($args);
	}

	/**
	 * method to get config value
	 * 
	 * @param mixed $keys array of keys or string divided by period
	 * @param mixed $defVal the value return if the keys not found in the config
	 * @return mixed the config value
	 * @deprecated since version 1.0
	 */
	public function getConfigValue($keys, $defVal) {
		$this->log->log("Billrun_Base::getConfigValue is deprecated; please use Billrun_Config::getConfigValue through factory::config()", Zend_Log::DEBUG);
		return Billrun_Factory::config()->getConfigValue($keys, $defVal);
	}

}
