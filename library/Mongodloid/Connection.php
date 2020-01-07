<?php

/**
 * @package         Mongodloid
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
class Mongodloid_Connection {

	protected $_connected = false;
	protected $_connection = null;
	protected $_persistent = false;
	protected $_server = '';
	protected $_dbs = array();
	protected static $instances;
	protected $username = '';
	protected $password = '';
	
	static public $availableReadPreferences = array(
		MongoClient::RP_PRIMARY,
		MongoClient::RP_PRIMARY_PREFERRED,
		MongoClient::RP_SECONDARY,
		MongoClient::RP_SECONDARY_PREFERRED,
		MongoClient::RP_NEAREST,
	);


	/**
	 * Method to get database instance
	 * 
	 * @param string $db the datainstace name
	 * @param string $user user to authenticate
	 * @param string $pass password to authenticate
	 * 
	 * @return Mongodloid_Db instance
	 */
	public function getDB($db, $user = false, $pass = false, array $options = array("connect" => TRUE)) {
		// casting int values that are passed from config (string)
		if (PHP_MAJOR_VERSION >= 7) {
			foreach ($options as $key => &$option) {
				if (is_numeric($option) && ($option == intval($option)) && stripos($key, 'timeout') !== FALSE) {
					settype($option, 'int');
				}
			}
		}
		if (!isset($this->_dbs[$db]) || !$this->_dbs[$db]) {
			if ($user) {
				$this->username = $user;
			}
			if ($pass) {
				$this->password = $pass;
			}
			$options['db'] = $db;
			$this->forceConnect($options);
			$newDb = $this->_connection->selectDB($db);

			$this->_dbs[$db] = $this->createInstance($newDb);
		}

		return $this->_dbs[$db];
	}

	/**
	 * create instance of the connection db
	 * 
	 * @param MongoDB $newDb The PHP Driver MongoDb instance
	 * 
	 * @return Mongodloid_Db instance
	 */
	protected function createInstance($newDb) {
		return new Mongodloid_Db($newDb, $this);
	}

	/**
	 * 	@throws MongoConnectionException
	 */
	public function forceConnect(array $options = array("connect" => TRUE)) {
		if ($this->_connected)
			return;

		if (!empty($this->username)) {
			$options['username'] = $this->username;
		}

		if (!empty($this->password)) {
			$options['password'] = $this->password;
		}
		
		if (isset($options['readPreference'])) {
			$readPreference = $options['readPreference'];
			unset($options['readPreference']);
		}

		// this can throw an Exception
		$this->_connection = new MongoClient($this->_server ? $this->_server : 'mongodb://localhost:27017', $options);

		if (!empty($readPreference) && defined('MongoClient::' . $readPreference)) {
			$this->_connection->setReadPreference(constant('MongoClient::' . $readPreference));
		}

		$this->_connected = true;
	}

	public function isConnected() {
		return $this->_connected;
	}

	public function isPersistent() {
		return $this->_persistent;
	}

	/**
	 * Singleton database connection
	 * 
	 * @param string $server
	 * @param string $port the port of the connection
	 * @param boolean $persistent set if the connection is persistent
	 * 
	 * @return Mongodloid_Connection
	 */
	public static function getInstance($server = '', $port = '', $persistent = false) {


		if (empty($port)) {
			$server_port = $server;
		} else {
			$server_port = $server . ':' . $port;
		}

		settype($persistent, 'boolean');

		if (!isset(self::$instances[$server_port]) || !self::$instances[$server_port]) {
			self::$instances[$server_port] = new static($server_port, $persistent);
		}

		return self::$instances[$server_port];
	}

	protected function __construct($server = '', $persistent = false) {
		$this->_persistent = (bool) $persistent;
		$this->_server = (string) $server;
	}

}
