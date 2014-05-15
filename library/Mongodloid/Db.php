<?php

/**
 * @package         Mongodloid
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
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
	 * method to get mongodb server version
	 * 
	 * @return string version
	 */
	public function getServerVersion() {
		$mongodb_info = $this->_db->command(array('buildinfo'=>true));
		return $mongodb_info['version'];
		
	}

	/**
	 * method to get mongodb server version
	 * 
	 * @param string $compare compare to version number
	 * @param string $operator operator how to compare (see PHP version_compare function)
	 * 
	 * @return string version if no compare return full number else boolean compare to supply version
	 */
	public function compareServerVersion($compare, $operator = null) {
		$serverVersion = $this->getServerVersion();
		if (!empty($operator)) {
			return version_compare($serverVersion, $compare, $operator);
		}
		
		return version_compare($serverVersion, $compare);
	}

}
