<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */
class Mongodloid_DB {

	protected $_db;
	protected $_connection;
	protected $_collections = array();

	public function getCollection($name) {
		if (!isset($this->_collections[$name]) || !$this->_collections[$name])
			$this->_collections[$name] = new Mongodloid_Collection($this->_db->selectCollection($name), $this);

		return $this->_collections[$name];
	}

	public function getName() {
		return (string) $this->_db;
	}

	public function __construct(MongoDb $db, Mongodloid_Connection $connection) {
		$this->_db = $db;
		$this->_connection = $connection;
	}

	public function command(array $command, array $options = array()) {
		return $this->_db->command($command, $options);
	}

}