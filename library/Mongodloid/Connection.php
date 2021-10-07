<?php

/**
 * @package         Mongodloid
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
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
		MongoDB\Driver\ReadPreference::PRIMARY ,
		MongoDB\Driver\ReadPreference::PRIMARY_PREFERRED ,
		MongoDB\Driver\ReadPreference::SECONDARY,
		MongoDB\Driver\ReadPreference::SECONDARY_PREFERRED,
		MongoDB\Driver\ReadPreference::NEAREST,
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
		// casting timeout (int) values that are passed from config (string)
		if (PHP_MAJOR_VERSION >= 7) {
			foreach ($options as $key => &$option) {
				if (stripos($key, 'timeout') !== FALSE && Billrun_Util::IsIntegerValue($option)) {
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
			$newDb = $this->_connection->selectDatabase($db);

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
			if (substr($readPreference, 0, strlen('RP_')) == 'RP_') {
				$readPreference = substr($readPreference, strlen('RP_'));
			}
			unset($options['readPreference']);
		}
		
		if (!empty($readPreference) && defined('MongoDB\Driver\ReadPreference::' . $readPreference)) {
			$options['readPreference'] = constant('MongoDB\Driver\ReadPreference::' . $readPreference);
		}

		if (isset($options['tags'])) {
			$options['readPreferenceTags'] = (array) $options['tags'];
			unset($options['tags']);
		}

		if (isset($options['context'])) {
			$driver_options = array(
				'context' => @stream_context_create($options['context'])
			);
			unset($options['context']);
			$options['ssl'] = true;
		} else {
			$driver_options = array();
		}
		
		if(isset($this->_server) && false === strpos($this->_server, '://')){
			$this->_server = 'mongodb://' . $this->_server;
		}
		
		// this can throw an Exception
		$this->_connection = new MongoDB\Client($this->_server ? $this->_server : 'mongodb://localhost:27017', $options, $driver_options);

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
	
	public static function clearInstances() {
		self::$instances = array();
	}

	protected function __construct($server = '', $persistent = false) {
		$this->_persistent = (bool) $persistent;
		$this->_server = (string) $server;
	}

	/**
	 * get PHP Mongodb client driver
	 * @return MongoDB\Client
	 */
	protected function getClient() {
		return $this->_connection;
	}
	
	/**
	 * method to start session and retrieve it
	 * 
	 * @return MongoDB\Driver\Session
	 */
	public function startSession() {
		return $this->getClient()->getManager()->startSession();
	}
	
	/**
	 * method to get the MongoDB servers
	 *
	 * @return array of MongoDB\Driver\Server instances to which this manager is connected.
	 */
	public function getServers() {
		return $this->getClient()->getManager()->getServers();
 	}

}

// deprecated classes; will be remove on version 6.0
if (!class_exists('MongoRegex')) {
	Class MongoRegex extends Mongodloid_Regex {}
}

if (!class_exists('MongoId')) {
	Class MongoId extends Mongodloid_Id {}
}

if (!class_exists('MongoDate')) {
	Class MongoDate extends Mongodloid_Date {}
}
