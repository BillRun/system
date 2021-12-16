<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing sender for ftp
 *
 * @package  Billing
 * @since    5.9
 */
class Billrun_Sender_Ssh extends Billrun_Sender {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'ssh';
	protected $port = '22';


	/**
	 * see parent::send()
	 */
	public function send($filePath) {
		Billrun_Factory::dispatcher()->trigger('beforeSSHSendFiles', array($this));
		$files = is_array($filePath) ? $filePath : array($filePath);
		$connectionSettings = $this->options;
		$hostAndPort = $connectionSettings['host'] . ':'. $this->port;
		$auth = array('password' => $connectionSettings['password']);
		$ssh = new Billrun_Ssh_Seclibgateway($hostAndPort, $auth, array());
		$connected = $ssh->connect($connectionSettings['user']);
		if (!$connected) {
			Billrun_Factory::log()->log("Cannot get SSH connector. details: " . print_R($connectionSettings, 1), Zend_Log::ERR);
			return false;
		}

		$remoteDirectory = rtrim(Billrun_Util::getIn($connectionSettings, 'remote_directory', ''), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		$ssh->mkdir($remoteDirectory);
		$ret = true;
		foreach ($files as $file) {
			if (empty($file) || !file_exists($file)) {
				Billrun_Factory::log()->log("Cannot get file " . $file, Zend_Log::ERR);
				$ret = false;
				continue;
			}
			$fileName = basename($file);
			$remoteFilePath = $remoteDirectory . $fileName;
			if (!$ssh->put($file, $remoteFilePath)){
				Billrun_Factory::log()->log("Cannot put file in SSH server. file: " . $file . ", directory: " . $remoteDirectory, Zend_Log::ERR);
				$ret = false;
			}
		}
		
		Billrun_Factory::dispatcher()->trigger('afterSSHSendFiles', array($this));
		return $ret;
	}

}
