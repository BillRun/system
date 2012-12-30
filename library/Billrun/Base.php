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
	 * the configuration of the system
	 * 
	 * @var configuration class
	 */
	protected $config;

	/**
	 * dispatcher of the plugin system
	 * 
	 * @var dispatcher class
	 */
	protected $dispatcher;

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
	 * constructor
	 * @param array $options
	 */
	public function __construct($options) {
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

		$class = get_called_class() . '_' . ucfirst($type);

		return new $class($args);
	}

}