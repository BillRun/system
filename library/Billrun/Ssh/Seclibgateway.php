<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billrun ssh security lib
 *
 * @package  Billrun SSH
 * @since    5.0
 * @uses Net_SFTP, Crypt_RSA, System_SSH_Agent
 */
class Billrun_Ssh_Seclibgateway implements Billrun_Ssh_Gatewayinterface {

	/**
	 * The host name of the server.
	 *
	 * @var string
	 */
	protected $host;

	/**
	 * The SSH port on the server.
	 *
	 * @var int
	 */
	protected $port = 22;

	/**
	 * The authentication credential set.
	 *
	 * @var array
	 */
	protected $auth;

	/**
	 * The filesystem instance.
	 *
	 * @var array
	 */
	protected $files;

	/**
	 * The SecLib connection instance.
	 *
	 * @var Net_SFTP
	 */
	protected $connection;

	/**
	 * Create a new gateway implementation.
	 *
	 * @param  string  $host
	 * @param  array   $auth
	 * @param  array  $files
	 * @return void
	 */
	public function __construct($host, array $auth, array $files) {
		$this->auth = $auth;
		$this->files = $files;
		$this->setHostAndPort($host);
	}

	/**
	 * Set the host and port from a full host string.
	 *
	 * @param  string  $host
	 * @return void
	 */
	protected function setHostAndPort($host) {
		if (!strpos($host, ':')) {
			$this->host = $host;
		} else {
			list($this->host, $this->port) = explode(':', $host);
			$this->port = (int) $this->port;
		}
	}

	/**
	 * Connect to the SSH server.
	 *
	 * @param  string  $username
	 * @return bool
	 */
	public function connect($username) {
		return $this->getConnection()->login($username, $this->getAuthForLogin());
	}

	/**
	 * Determine if the gateway is connected.
	 *
	 * @return bool
	 */
	public function connected() {
		return $this->getConnection()->isConnected();
	}

	/**
	 * Run a command against the server (non-blocking).
	 *
	 * @param  string  $command
	 * @return void
	 */
	public function run($command) {
		return $this->getConnection()->exec($command);
	}

	/**
	 * Download the contents of a remote file.
	 *
	 * @param  string  $remote
	 * @param  string  $local
	 * @return void
	 */
	public function get($remote, $local) {
		$this->getConnection()->get($remote, $local);
	}

	/**
	 * Get the contents of a remote file.
	 *
	 * @param  string  $remote
	 * @return string
	 */
	public function getString($remote) {
		return $this->getConnection()->get($remote);
	}

	/**
	 * Upload a local file to the server.
	 *
	 * @param  string  $local
	 * @param  string  $remote
	 * @return void
	 */
	public function put($local, $remote) {
		return $this->getConnection()->put($remote, $local, \phpseclib\Net\SFTP::SOURCE_LOCAL_FILE);
	}

	/**
	 * Upload a string to to the given file on the server.
	 *
	 * @param  string  $remote
	 * @param  string  $contents
	 * @return void
	 */
	public function putString($remote, $contents) {
		$this->getConnection()->put($remote, $contents);
	}

	/**
	 * Get the next line of output from the server.
	 *
	 * @return string|null
	 */
	public function nextLine() {
		$value = $this->getConnection()->_get_channel_packet(\phpseclib\Net\SSH2::CHANNEL_EXEC);

		return $value === true ? null : $value;
	}

	/**
	 * Get list of files in directory
	 *
	 * @param  string  $dir
	 * @param  bool  $recursive
	 * @return Array
	 */
	public function getListOfFiles($dir, $recursive = false) {
		$files = array();
		$rootFolders = $this->getConnection()->nlist($dir, $recursive);
		if (empty($rootFolders)) {
			Billrun_Factory::log("No files received");
			return array();
		}
		Billrun_Factory::log()->log("Finished searching for files", Zend_Log::INFO);
		$check_is_numeric = Billrun_Factory::config()->getConfigValue('Seclibgateway.check_is_numeric');
		foreach ($rootFolders as $folder) {
			if ($check_is_numeric && !is_numeric($folder)) {
				continue;
			}
			if (is_dir($folder) && ($folder != '.') && ($folder != '..') ) {
				$_files = $this->getConnection()->nlist($dir . $folder, $recursive);
				// Only adds files (not folders or hidden)
				foreach ($_files as $file) {
					if (strpos($file, '.') !== 0) {
						$files[] = $folder . '/' . $file;
					}
				}
			} else {
				if (strpos($folder, '.') !== 0) {
					$files[] = $folder;
				}
			}
			
		}

		return $files;
	}
	

	/**
	 * Get files' timestamp
	 *
	 * @param  string  $file_path
	 * @return timestamp
	 */
	public function getTimestamp($file_path) {
		return $this->getConnection()->filemtime($file_path);
	}

	/**
	 * Get files' size
	 *
	 * @param  string  $file_path
	 * @return size
	 */
	public function getFileSize($file_path) {
		return $this->getConnection()->size($file_path);
	}

	/**
	 * Get files' timestamp
	 *
	 * @param  string  $file_path
	 * @return void
	 */
	public function deleteFile($file_path) {
		return $this->getConnection()->delete($file_path);
	}

	/**
	 * Get the authentication object for login.
	 *
	 * @return Crypt_RSA|System_SSH_Agent|string
	 * @throws InvalidArgumentException
	 */
	protected function getAuthForLogin() {
		if ($this->useAgent()) {
			return $this->getAgent();
		}

		// If a "key" was specified in the auth credentials, we will load it into a
		// secure RSA key instance, which will be used to connect to the servers
		// in place of a password, and avoids the developer specifying a pass.
		elseif ($this->hasRsaKey()) {
			return $this->loadRsaKey($this->auth);
		}

		// If a plain password was set on the auth credentials, we will just return
		// that as it can be used to connect to the server. This will be used if
		// there is no RSA key and it gets specified in the credential arrays.
		elseif (isset($this->auth['password'])) {
			return $this->auth['password'];
		}

		throw new InvalidArgumentException('Password / key is required.');
	}

	/**
	 * Determine if an RSA key is configured.
	 *
	 * @return bool
	 */
	protected function hasRsaKey() {
		$hasKey = (isset($this->auth['key']) && trim($this->auth['key']) != '');

		return $hasKey || (isset($this->auth['keytext']) && trim($this->auth['keytext']) != '');
	}

	/**
	 * Load the RSA key instance.
	 *
	 * @param  array  $auth
	 * @return Crypt_RSA
	 */
	protected function loadRsaKey(array $auth) {
		//$key = $this->getKey($auth);
		$key = new phpseclib\Crypt\RSA();
		$key->loadKey($this->readRsaKey($auth));
		//$key->loadKey($this->readRsaKey($auth));
		//$key->setParams(array('public_key' => $this->readRsaKey($auth)));
		return $key;
	}

	/**
	 * Read the contents of the RSA key.
	 *
	 * @param  array  $auth
	 * @return string
	 */
	protected function readRsaKey(array $auth) {
		if (isset($auth['key']) && is_file($auth['key'])) {
			return file_get_contents($auth['key']);
		}

		return $auth['keytext'];
	}

	/**
	 * Create a new RSA key instance.
	 *
	 * @param  array  $auth
	 * @return Crypt_RSA
	 */
	protected function getKey(array $auth) {
		$key = $this->getNewKey();
		//$key->setParams($auth);
		//$key->setPassword(array_get($auth, 'keyphrase'));
		//$key->loadKey(file_get_contents('privatekey'));

		return $key;
	}

	/**
	 * Determine if the SSH Agent should provide an RSA key.
	 *
	 * @return bool
	 */
	protected function useAgent() {
		return isset($this->auth['agent']) && $this->auth['agent'] === true;
	}

	/**
	 * Get a new SSH Agent instance.
	 *
	 * @return System_SSH_Agent
	 */
	public function getAgent() {
		return new System_SSH_Agent;
	}

	/**
	 * Get a new RSA key instance.
	 *
	 * @return Crypt_RSA
	 */
	public function getNewKey() {
		return new phpseclib\Crypt\RSA;
		//return Crypt_RSA::factory();
	}

	/**
	 * Get the exit status of the last command.
	 *
	 * @return int|bool
	 */
	public function status() {
		return $this->getConnection()->getExitStatus();
	}

	/**
	 * Get the host used by the gateway.
	 *
	 * @return string
	 */
	public function getHost() {
		return $this->host;
	}

	/**
	 * Get the port used by the gateway.
	 *
	 * @return int
	 */
	public function getPort() {
		return $this->port;
	}

	/**
	 * Get the underlying Net_SFTP connection.
	 *
	 * @return Net_SFTP
	 */
	public function getConnection() {
		if ($this->connection) {
			return $this->connection;
		}
		return $this->connection = new phpseclib\Net\SFTP($this->host, $this->port);
	}
	
	/**
	 * Rename a remote file.
	 * 
	 * @return string
	 */
	public function renameFile($oldname, $newname) {
		return $this->getConnection()->rename($oldname, $newname);
	}
	
	/**
	 * Change working directory.
	 * 
	 * @return boolean
	 */
	public function changeDir($newPath) {
		return $this->getConnection()->chdir($newPath);
	}
        
        
        /**
	 * Verify that the path is a file. 
	 * @return boolean true if the path is a file false otherwise.
	 */
        public function isFile($path) {
		return $this->getConnection()->is_file($path);
	}

	/**
	 * Disconnect from gateway
	 */
	public function disconnect() {
		if ($this->connected()) {
			$this->getConnection()->disconnect();
		}
	}
}
