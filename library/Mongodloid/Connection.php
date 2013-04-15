<?php

/**
Copyright (c) 2009, Valentin Golev
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.

    * Redistributions in binary form must reproduce the above copyright notice,
      this list of conditions and the following disclaimer in the documentation
      and/or other materials provided with the distribution.

    * The names of contributors may not be used to endorse or promote products
      derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */
class Mongodloid_Connection {

	protected $_connected = false;
	protected $_connection = null;
	protected $_persistent = false;
	protected $_server = '';
	protected $_dbs = array();

	public function getDB($db) {
		if (!isset($this->_dbs[$db]) || !$this->_dbs[$db]) {
			$this->forceConnect();
			$this->_dbs[$db] = new Mongodloid_DB($this->_connection->selectDB($db), $this);
		}

		return $this->_dbs[$db];
	}

	/**
	 * 	@throws MongoConnectionException
	 */
	public function forceConnect() {
		if ($this->_connected)
			return;

		// this can throw an Exception
		$this->_connection = new Mongo($this->_server ? $this->_server : 'localhost:27017', array());

		$this->_connected = true;
	}

	public function isConnected() {
		return $this->_connected;
	}

	public function isPersistent() {
		return $this->_persistent;
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
			$instances[$server] = new Mongodloid_Connection($server, $persistent);
		}

		return $instances[$server];
	}

	protected function __construct($server = '', $persistent = false) {
		$this->_persistent = (bool) $persistent;
		$this->_server = (string) $server;
	}

}