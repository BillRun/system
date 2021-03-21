<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billrun ssh gateway interface
 *
 * @package  Billrun SSH
 * @since    5.0
 */
interface Billrun_Ssh_Gatewayinterface {

	/**
	 * Connect to the SSH server.
	 *
	 * @param  string  $username
	 * @return void
	 */
	public function connect($username);

	/**
	 * Determine if the gateway is connected.
	 *
	 * @return bool
	 */
	public function connected();

	/**
	 * Run a command against the server (non-blocking).
	 *
	 * @param  string  $command
	 * @return void
	 */
	public function run($command);

	/**
	 * Upload a local file to the server.
	 *
	 * @param  string  $local
	 * @param  string  $remote
	 * @return void
	 */
	public function put($local, $remote);

	/**
	 * Upload a string to to the given file on the server.
	 *
	 * @param  string  $remote
	 * @param  string  $contents
	 * @return void
	 */
	public function putString($remote, $contents);

	/**
	 * Get the next line of output from the server.
	 *
	 * @return string|null
	 */
	public function nextLine();
		
	/**
	 * Get list of files in directory
	 *
	 * @param  string  $dir
	 * @param  bool  $recursive
	 * @return Array
	 */
	public function getListOfFiles($dir, $recursive = false);
	
	/**
	 * Get files' timestamp
	 *
	 * @param  string  $file_path
	 * @return timestamp
	 */
	public function getTimestamp($file_path);
		
	/**
	 * Get files' size
	 *
	 * @param  string  $file_path
	 * @return size
	 */
	public function getFileSize($file_path);
	
	/**
	 * Get files' timestamp
	 *
	 * @param  string  $file_path
	 * @return void
	 */
	public function deleteFile($file_path);

	/**
	 * Get the exit status of the last command.
	 *
	 * @return int|bool
	 */
	public function status();

}
