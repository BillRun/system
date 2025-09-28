<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
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
	protected $sshConfig = false;
	protected $backup = false;
	protected $checkReceivedSize = true;
	protected $connect_type;

	public function __construct($options) {
		parent::__construct($options);
		$this->sshConfig = $options['receiver']['connections'];

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
		$ret = array();
		$useNameAsSubfolder = $this->areConnectionNamesUnique();
		
		foreach ($this->sshConfig as $config) {

			// Check if private key exist
			if (isset($config['key'])) {
				$directoryPath = 'files/keys/input_processors/';
				$sharedDirectoryPath = Billrun_Util::getBillRunSharedFolderPath($directoryPath);
				$auth = array(
					'key' => $sharedDirectoryPath . $config['key'],
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
			
			$ssh_path = isset($config['remote_directory']) ? $config['remote_directory'] : '/';
            $recursive_mode = isset($config['recursive_mode']) ? $config['recursive_mode'] : false;
			$this->filenameRegex = !empty($config['filename_regex']) ? $config['filename_regex'] : '/.*/';
			$this->ssh = new Billrun_Ssh_Seclibgateway($hostAndPort, $auth, array());
			Billrun_Factory::log()->log("Connecting to SFTP server: " . $this->ssh->getHost() , Zend_Log::INFO);
			$connected = $this->ssh->connect($config['user']);
			 if (!$connected){
				 Billrun_Factory::log()->log("SSH: Can't connect to $hostAndPort", Zend_Log::ALERT);
				 return $ret;
			 }
			Billrun_Factory::log()->log("Success: Connected to: " . $this->ssh->getHost() , Zend_Log::INFO);
			$this->ssh->changeDir($ssh_path);
			try {
				Billrun_Factory::log()->log("Searching for files: ", Zend_Log::INFO);
				$files = $this->ssh->getListOfFiles($ssh_path, $recursive_mode);
	
				$type = static::$type;
				$count = 0;
				$targetPath = $this->workspace;

				if (substr($targetPath, -1) != '/') {
					$targetPath .= '/';
				}

				$stamp_fields = $this->getExtraStampFieldsValues($config);

				foreach ($files as $file) {
					Billrun_Factory::dispatcher()->trigger('beforeFileReceive', array($this, &$file, $type));
                    if (!$this->ssh->isFile($ssh_path . "/" . $file)) {
						Billrun_Factory::log("SSH: " . $file . " is not a file", Zend_Log::DEBUG);
						continue;
					}
					Billrun_Factory::log()->log("SSH: Found file " . $file, Zend_Log::DEBUG);
					$filename = basename($file);
					if (!$this->ssh->isFile($ssh_path."/" . $file)) {
							Billrun_Factory::log("SSH: " . $file . " is not a file", Zend_Log::DEBUG);
							continue;
					}
					if (!$this->isFileValid($filename, '')) {
						Billrun_Factory::log()->log($filename . " is not valid.", Zend_Log::DEBUG);
						continue;
					}

					// Lock
					if (!$this->lockFileForReceive($filename, $type, $stamp_fields)) {
						Billrun_Factory::log('File ' . $filename . ' has been received already', Zend_Log::INFO);
						continue;
					}
					
					// Copy file from remote directory
					$fileData = $this->getFileLogData($filename, $type, $stamp_fields);

					Billrun_Factory::log()->log("SSH: Download file " . $file, Zend_Log::INFO);

					$sourcePath = $ssh_path;
					if (substr($sourcePath, -1) != '/') {
						$sourcePath .= '/';
					}


					if (!empty($stamp_fields)) {
						$stampFieldsHash = md5(serialize($stamp_fields));
						$fileData['path'] = rtrim($this->workspace, '/') . '/input_processors_receives/' . $type . '/' . $stampFieldsHash . '/' . $file;
					} else {
						$fileData['path'] = $targetPath . $file;
					}

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
						Billrun_Factory::log()->log("SSH: file was not saved correctly " . $file, Zend_Log::WARN);
						continue;
					}

					// Preserve timestamp
					if ($this->preserve_timestamps) {
						$timestamp = $this->getSourceTimestamp($sourceFile);

						if ($timestamp !== FALSE) {
							Billrun_Util::setFileModificationTime($fileData['path'], $timestamp);
						}
					}

					if (!empty($this->backupPaths)) {
						$backupDestinationPaths = $this->backupPaths;
						if (!empty($stamp_fields)) {
							$subfolderName = $useNameAsSubfolder ? $config['name'] : md5(serialize($stamp_fields));
							$paths = is_array($this->backupPaths) ? $this->backupPaths : [$this->backupPaths];
							$backupDestinationPaths = array_map(function ($path) use ($subfolderName) {
								return rtrim($path, '/') . '/' . $subfolderName;
							}, $paths);
						}
						$backedTo = $this->backup($fileData['path'], $file, $backupDestinationPaths);
						$fileData['backed_to'] = $backedTo;
					}

					// Log to DB
					if ($this->logDB($fileData)) {
						$ret[] = $fileData['path'];
						$count++;

						// Add extension
						if (isset($config['received_extension']) && $config['received_extension']) {
							$renamedFile = $file . $config['received_extension'];
							Billrun_Factory::log()->log("SSH: Renaming file {$file} to \"{$renamedFile}\" on remote host.", Zend_Log::INFO);
							if (!$this->renameRemoteFile($ssh_path . '/' . $file, $ssh_path . '/' . $renamedFile)) {
								Billrun_Factory::log()->log("SSH: Failed to rename file {$file} to \"{$renamedFile}\" on remote host.", Zend_Log::WARN);
							}
							if (isset($config['delete_received']) && $config['delete_received']) {
								Billrun_Factory::log()->log(
									"SSH: `received_extension` is set. Skipping file deletion (`delete_received` is ignored).",
									Zend_Log::INFO
								);
							}
						}

						// Delete from remote
						else if (isset($config['delete_received']) && $config['delete_received']) {
							Billrun_Factory::log()->log("SSH: Deleting file {$file} from remote host ", Zend_Log::INFO);
							if(!$this->deleteRemote($ssh_path . '/' . $file)) {
								Billrun_Factory::log()->log("SSH: Failed to delete file: " . $file, Zend_Log::WARN);
							}
						}
					}

					// Check limit
					if ($count >= $this->limit) {
						break;
					}

					Billrun_Factory::dispatcher()->trigger('afterFileReceived', array($this, $file, &$fileData));
				}
			} catch (Exception $e) {
				Billrun_Factory::log()->log("SSH: Fail when downloading. with exception : " . $e, Zend_Log::DEBUG);
				$ret = array();
			} finally {
				$this->ssh->disconnect();
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
	 * Getter for SFTP receiver connection.
	 * 
	 * @return Billrun_Ssh_Seclibgateway
	 */
	public function getReceiver() {
		return $this->ssh;
	}

	/** Getter for filename regex
	 * 
	 * @return string
	 */
	public function getFilenameRegex() {
		return $this->filenameRegex;
	}
	
	/**
	 * delete file from remote host
	 * @param String $file_path
	 * @return boolean
	 */
	protected function deleteRemote($file_path) {
		return $this->ssh->deleteFile($file_path);
	}

	/**
	 * Rename a file on the remote server.
	 *
	 * @param string $oldName The current name of the file on the remote server.
	 * @param string $newName The new name for the file on the remote server.
	 * @return boolean True if renaming was successful, false otherwise.
	 */
	protected function renameRemoteFile($oldName, $newName)
	{
		return $this->ssh->renameFile($oldName, $newName);
	}

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
	
	/**
	 * Verify that the file is a valid file. 
	 * @return boolean false if the file name should not be received true if it should.
	 */
	protected function isFileValid($filename, $path) {
		return preg_match($this->filenameRegex, $filename);
	}

	/**
     * Builds an array of the extra stamp fields with their actual values.
     *
     * @param array $config The connection-specific configuration.
     * @return array The array of stamp fields with their values.
     */
	protected function getExtraStampFieldsValues(array $config)
	{
		$stamp_fields = [];
		if (!empty($this->extra_stamp_fields)) {
			foreach ($this->extra_stamp_fields as $field_name) {
				if (!empty($config[$field_name])) {
					$stamp_fields[$field_name] = $config[$field_name];
				} else {
					Billrun_Factory::log()->log(
						"Billrun_Receiver_Ssh: The stamp_field '{$field_name}' was not found or is empty in this receiver's connection details.",
						Zend_Log::ALERT
					);
				}
			}
		}
		return $stamp_fields;
	}

	/**
	 * Checks if all SSH connections have unique and valid names.
	 *
	 * This ensures that each connection has a 'name' key and that
	 * no two connections share the same name.
	 *
	 * @return boolean True if all names are unique, false otherwise.
	 */
	protected function areConnectionNamesUnique()
	{
		if (empty($this->sshConfig) || empty($this->extra_stamp_fields)) {
			return false;
		}

		$configNames = array_column($this->sshConfig, 'name');
		$allNamesExist = (count($configNames) === count($this->sshConfig));
		$areNamesUnique = (count($configNames) === count(array_unique($configNames)));

		return $allNamesExist && $areNamesUnique;
	}
}
