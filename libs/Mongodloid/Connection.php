<?php

/**
 * @package			Billing Mongo Connection
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */
require_once 'DB.php';

class Mongodloid_Connection
{

	private $_connected = false;
	private $_connection = null;
	private $_persistent = false;
	private $_server = '';
	private $_dbs = array();

	public function getDB($db)
	{
		if (!isset($this->_dbs[$db]) || !$this->_dbs[$db])
		{
			$this->forceConnect();
			$this->_dbs[$db] = new Mongodloid_DB($this->_connection->selectDB($db), $this);
		}

		return $this->_dbs[$db];
	}

	/**
	 * 	@throws MongoConnectionException
	 */
	public function forceConnect()
	{
		if ($this->_connected)
			return;

		// this can throw an Exception
		$this->_connection = new Mongo($this->_server ? $this->_server : 'localhost:27017', true, $this->_persistent);

		$this->_connected = true;
	}

	public function isConnected()
	{
		return $this->_connected;
	}

	public function isPersistent()
	{
		return $this->_persistent;
	}

	public static function getInstance($server = '', $port = '', $persistent = false)
	{
		static $instances;

		if (!$instances)
		{
			$instances = array();
		}

		if (is_bool($server))
		{
			$persistent = $server;
			$server = $port = '';
		}

		if (is_bool($port))
		{
			$persistent = $port;
			$port = '';
		}

		if (is_numeric($port) && $port)
		{
			$server .= ':' . $port;
		}

		$persistent = (bool) $persistent;
		$server = (string) $server;

		if (!isset($instances[$server]) || !$instances[$server])
		{
			$instances[$server] = new Mongodloid_Connection($server, $persistent);
		}

		return $instances[$server];
	}

	private function __construct($server = '', $persistent = false)
	{
		$this->_persistent = (bool) $persistent;
		$this->_server = (string) $server;
	}

}