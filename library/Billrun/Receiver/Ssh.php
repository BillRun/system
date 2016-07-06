<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing receiver for SSH
 *
 * @package  Billing
 * @since    5.0
 */
class Billrun_Receiver_Ssh extends Billrun_Receiver {

	static protected $type = 'ssh';
	protected $ssh = null;
	protected $ssh_path = '/';
	protected $sshConfig = false;
	protected $backup = false;
	protected $checkReceivedSize = true;
	protected $connect_type;

	public function __construct($options) {
		parent::__construct($options);
		$this->sshConfig = isset($options['ssh']['host']) ? array($options['ssh']) : $options['ssh'];

		if (isset($options['ssh']['remote_directory'])) {
			$this->ssh_path = $options['ssh']['remote_directory'];
		}

		if (isset($options['workspace'])) {
			$this->workspace = $options['workspace'];
		}

		if (isset($options['backup_path'])) {
			$this->backup = $options['backup_path'];
		}

		if (isset($options['received']['check_received_size'])) {
			$this->checkReceivedSize = $options['received']['check_received_size'];
		}
	}

	/**
	 * method to receive files through ssh
	 * 
	 * @return array list of the files received
	 */
	public function receive() {
		foreach ($this->sshConfig as $config) {

			// Check if private key exist
			if (isset($config['key'])) {
				$auth = array(
					'key' => $config['key'],
				);
			} else {
				$auth = array(
					'password' => $config['password'],
				);
			}

			$hostAndPort = $config['host'];
			if (isset($config['port'])) {
				$hostAndPort .= ':'.$config['port'];
			}
			
			$this->ssh = new Billrun_Ssh_Seclibgateway($hostAndPort, $auth, array());
			$this->ssh->connect($config['user']);

			try {
				$ret = array();
				$files = $this->ssh->getListOfFiles($this->ssh_path, true);
	
				$type = static::$type;
				$count = 0;
				$targetPath = $this->workspace;

				if (substr($targetPath, -1) != '/') {
					$targetPath .= '/';
				}

				foreach ($files as $file) {
					Billrun_Factory::dispatcher()->trigger('beforeFileReceive', array($this, $file));
					Billrun_Factory::log()->log("SSH: Found file " . $file, Zend_Log::INFO);

					if (!$this->isFileValid($file, '')) {
						Billrun_Factory::log()->log($file . " is not valid.", Zend_Log::INFO);
						continue;
					}

					// Lock
					if (!$this->lockFileForReceive($file, $type)) {
						Billrun_Factory::log('File ' . $file . ' has been received already', Zend_Log::INFO);
						continue;
					}

					// Copy file from remote directory
					$fileData = $this->getFileLogData($file, $type);

					Billrun_Factory::log()->log("SSH: Download file " . $file, Zend_Log::INFO);

					$sourcePath = $this->ssh_path;
					if (substr($sourcePath, -1) != '/') {
						$sourcePath .= '/';
					}

					$fileData['path'] = $targetPath . $file;

					if (!file_exists(dirname($fileData['path']))) {
						mkdir(dirname($fileData['path']), 0777, true);
					}

					$sourceFile = $sourcePath . $file;

					if ($this->ssh->get($sourceFile, $fileData['path']) === FALSE) {
						Billrun_Factory::log()->log("SSH: failed to download " . $file, Zend_Log::ALERT);
						continue;
					}

					// Checks that file received correctly
					if (!$this->isFileReceivedCorrectly($sourceFile, $fileData['path'])) {
						Billrun_Factory::log()->log("SSH: file was not saved correctly " . $file, Zend_Log::ALERT);
						continue;
					}

					// Preserve timestamp
					if ($this->preserve_timestamps) {
						$timestamp = $this->getSourceTimestamp($sourceFile);

						if ($timestamp !== FALSE) {
							Billrun_Util::setFileModificationTime($fileData['path'], $timestamp);
						}
					}

					// Backup
					if (!empty($this->backupPaths)) {
						$backedTo = $this->backup($fileData['path'], $file, $this->backupPaths);
						$fileData['backed_to'] = $backedTo;
					}

					// Log to DB
					if ($this->logDB($fileData)) {
						$ret[] = $fileData['path'];
						$count++;

						// Delete from remote
						if (isset($config['delete_remote_after_receive']) && $config['delete_remote_after_receive']) {
							Billrun_Factory::log()->log("SSH: Deleting file {$file} from remote host ", Zend_Log::INFO);
							$this->deleteRemote($fileData['file_name']);
						}
					}

					// Check limit
					if ($count >= $this->limit) {
						break;
					}

					Billrun_Factory::dispatcher()->trigger('afterFileReceived', array($this, $file));
				}
			} catch (Exception $e) {
				Billrun_Factory::log()->log("SSH: Fail when downloading. with exception : " . $e, Zend_Log::DEBUG);
				return array();
			}
		}

		return $ret;
	}

	/**
	 * gets a file timestamp
	 * @param String $file_path
	 */
	protected function getSourceTimestamp($file_path) {
		return $this->ssh->getTimestamp($file_path);
	}

	/**
	 * delete file from remote host
	 * @param String $file_path
	 */
//	protected function deleteRemote($file_path) {
//		$this->ssh->deleteFile($file_path);
//	}

	/**
	 * check if the received file was correctly received
	 * @param String $remoteFile
	 * @param String $localFilePath
	 */
	protected function isFileReceivedCorrectly($remoteFile, $localFilePath) {
		if ($this->checkReceivedSize) {
			$local_size = filesize($localFilePath);
			$remote_size = $this->ssh->getFileSize($remoteFile);
			if ($local_size !== $remote_size) {
				Billrun_Factory::log()->log("SSH: The remote file size (" . $remote_size . ") is different from local file size (" . $local_size . ")", Zend_Log::ERR);
				return false;
			}
		}
		return true;
	}

}
