<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing db class
 *
 * @package  Db
 * @since    0.5
 */
class Billrun_Connection extends Mongodloid_Connection {

	protected static $instances;
	/**
	 * Method to get database instance
	 * 
	 * @param string $db the datainstace name
	 * 
	 * @return Billrun_Db instance
	 */
	public function getDB($db, $user = false , $pass = false) {
		if (!isset($this->_dbs[$db]) || !$this->_dbs[$db]) {
			$this->forceConnect();
			$newDb = $this->_connection->selectDB($db);
			if($user) {
				$newDb->authenticate($user,$pass);
			}
			$this->_dbs[$db] = new Billrun_DB($newDb, $this);
		}

		return $this->_dbs[$db];
	}

	
	/**
	 * Singleton database connection
	 * 
	 * @param string $server
	 * @param string $port the port of the connection
	 * @param boolean $persistent set if the connection is persistent
	 * 
	 * @return Billrun_Connection
	 */
	public static function getInstance($server = '', $port = '', $persistent = false) {


		$server_port = $server . ':' . $port;
		settype($server_port, 'int');
		settype($persistent, 'boolean');

		if (!isset(self::$instances[$server_port]) || !self::$instances[$server_port]) {
			self::$instances[$server_port] = new self($server_port, $persistent);
		}

		return self::$instances[$server_port];
	}

}