<?php

/**
 * @package         Mongodloid
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
class Mongodloid_Db {

	protected $_db;
	protected $_connection;
	protected $_collections = array();

	public function __construct(MongoDb $db, Mongodloid_Connection $connection) {
		$this->_db = $db;
		$this->_connection = $connection;
	}

	public function getCollection($name) {
		if (!isset($this->_collections[$name]) || !$this->_collections[$name])
			$this->_collections[$name] = new Mongodloid_Collection($this->_db->selectCollection($name), $this);

		return $this->_collections[$name];
	}

	public function getName() {
		return (string) $this->_db;
	}

	public function command(array $command, array $options = array()) {
		return $this->_db->command($command, $options);
	}

	/**
	 * method to get dbStats or collection stats (for the later see the stats method in collection class)
	 * 
	 * @param array $stats which stats to pull
	 * @param mixed $item return only specific property of stats
	 * 
	 * @return mixed the whole stats or just one item of it
	 */
	public function stats(array $stats = array('dbStats' => 1), $item = null) {
		$ret = $this->_db->command($stats);

		if (is_null($item)) {
			return $ret;
		}

		if (isset($ret[$item])) {
			return $ret[$item];
		}
	}

	/**
	 * method to copmare version of mongo (server or client)
	 * 
	 * @param string $compare compare to version number
	 * @param string $source what version to compare to (server or client)
	 * @param string $operator operator how to compare (see PHP version_compare function)
	 * 
	 * @return mixed see PHP documentation of version_compare function
	 */
	protected function compareMongoVersion($compare, $source = 'server', $operator = null) {
		if (strtolower($source) == 'server') {
			$version = $this->getServerVersion();
		} else {
			$version = $this->getClientVersion();
		}

		if (!empty($operator)) {
			return version_compare($version, $compare, $operator);
		}

		return version_compare($version, $compare);
	}

	/**
	 * method to get mongodb server version
	 * 
	 * @param string $compare compare to version number
	 * @param string $operator operator how to compare (see PHP version_compare function)
	 * 
	 * @return mixed see PHP documentation of version_compare function
	 */
	public function compareServerVersion($compare, $operator = null) {
		return $this->compareMongoVersion($compare, 'server', $operator);
	}

	/**
	 * method to get mongodb server version
	 * 
	 * @param string $compare compare to version number
	 * @param string $operator operator how to compare (see PHP version_compare function)
	 * 
	 * @return mixed see PHP documentation of version_compare function
	 */
	public function compareClientVersion($compare, $operator = null) {
		return $this->compareMongoVersion($compare, 'client', $operator);
	}

	/**
	 * method to get mongodb client version
	 * 
	 * @return string version
	 */
	public function getClientVersion() {
		return MongoClient::VERSION;
	}

	/**
	 * method to get mongodb server version
	 * 
	 * @return string version
	 */
	public function getServerVersion() {
		$mongodb_info = $this->_db->command(array('buildinfo' => true));
		return $mongodb_info['version'];
	}
	
	/**
	 * Change the default number size in mongo to long or regular (64/32 bit) size.
	 * @param int $status either 1 to turn on or 0 for off
	 * @deprecated since version 4.0
	 */
	public function setMongoNativeLong($status = 1) {
		if ($status == 0 && $this->compareServerVersion('2.6', '>=') === true) {
			return;
		}
		ini_set('mongo.native_long', $status);
	}

	/**
	 * method to start session and retrieve it
	 * 
	 * @return MongoDB\Driver\Session
	 */
	public function startSession() {
		if ($this->isStandalone()) {
			return false;
		}
		return $this->_connection->startSession();
	}

	/**
	 * method to get the mongo servers
	 *
	 * @return MongoDB\Driver\Session
	 */
	public function getServers() {
		return $this->_connection->getServers();
	}
	
	/**
	 * method to check if the db server is standalone (not mongos and not replica-set)
	 * 
	 * @return boolean true if standalone else false
	 */
	public function isStandalone() {
		$servers = $this->getServers();
		return $servers[0]->getType() === MongoDB\Driver\Server::TYPE_STANDALONE;
	}

}
