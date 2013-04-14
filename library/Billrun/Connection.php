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

	public function getDB($db) {
		if (!isset($this->_dbs[$db]) || !$this->_dbs[$db]) {
			$this->forceConnect();
			$this->_dbs[$db] = new Billrun_DB($this->_connection->selectDB($db), $this);
		}

		return $this->_dbs[$db];
	}

	public static function getInstance($server = '', $port = '', $persistent = false) {
		static $instances;

		if (!$instances) {
			$instances = array();
		}

		if (is_bool($server)) {
			$persistent = $server;
			$server = $port = '';
		}

		if (is_bool($port)) {
			$persistent = $port;
			$port = '';
		}

		if (is_numeric($port) && $port) {
			$server .= ':' . $port;
		}

		$persistent = (bool) $persistent;
		$server = (string) $server;

		if (!isset($instances[$server]) || !$instances[$server]) {
			$instances[$server] = new Billrun_Connection($server, $persistent);
		}

		return $instances[$server];
	}

}