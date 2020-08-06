<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing payment gateways connection class
 *
 * @since    5.10
 */
class Billrun_PaymentGateway_Connection_Ssh extends Billrun_PaymentGateway_Connection {
	
	protected static $type = 'ssh';
	protected $port = '22';
	protected $checkReceivedSize = true;
	protected $source;

	public function __construct($options) {
		parent::__construct($options);
		$hostAndPort = $this->host . ':'. $this->port;
		$auth = array(
			'password' => $this->password,
		);
		$this->source = isset($options['type']) ? $options['type'] : self::$type;
		$this->connection = new Billrun_Ssh_Seclibgateway($hostAndPort, $auth, array());
	}

	public function receive() {
		if (empty($this->connection)) {
			Billrun_Factory::log("Missing connection", Zend_Log::DEBUG);
			return false;
		}
		$ret = array();
		$path = isset($this->remoteDir) ? $this->remoteDir : '/';
		Billrun_Factory::log()->log("Connecting to SFTP server: " . $this->connection->getHost() , Zend_Log::INFO);
		$connected = $this->connection->connect($this->username);
		 if (!$connected){
			 Billrun_Factory::log()->log("SSH: Can't connect to server", Zend_Log::ALERT);
			 return $ret;
		 }
		Billrun_Factory::log()->log("Success: Connected to: " . $this->connection->getHost() , Zend_Log::INFO);
		$this->connection->changeDir($path);
		try {
			Billrun_Factory::log()->log("Searching for files: ", Zend_Log::INFO);
			$files = $this->connection->getListOfFiles($path, true);
			$type = $this->source;
			$count = 0;
			$targetPath = $this->workspace;
			if (substr($targetPath, -1) != '/') {
				$targetPath .= '/';
			}
			foreach ($files as $file) {
				Billrun_Factory::dispatcher()->trigger('beforeFileReceive', array($this, &$file, $type));
				Billrun_Factory::log()->log("SSH: Found file " . $file, Zend_Log::DEBUG);
				if (!$this->isFileValid($file)) {
					Billrun_Factory::log()->log($file . " is not valid.", Zend_Log::DEBUG);
					continue;
				}
				// Lock
				$moreFields = !empty($this->fileType) ? array('pg_file_type' => $this->fileType) : array();
				if (!$this->lockFileForReceive($file, $type, $moreFields)) {
					Billrun_Factory::log('File ' . $file . ' has been received already', Zend_Log::INFO);
					continue;
				}
				// Copy file from remote directory
				$fileData = $this->getFileLogData($file, $type, $moreFields);
				Billrun_Factory::log()->log("SSH: Download file " . $file, Zend_Log::INFO);
				$sourcePath = $path;
				if (substr($sourcePath, -1) != '/') {
					$sourcePath .= '/';
				}
				$fileData['path'] = $targetPath . $file;
				if (!file_exists(dirname($fileData['path']))) {
					mkdir(dirname($fileData['path']), 0777, true);
				}
				$sourceFile = $sourcePath . $file;
				if ($this->connection->get($sourceFile, $fileData['path']) === FALSE) {
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
					if (isset($config['delete_received']) && $config['delete_received']) {
						Billrun_Factory::log()->log("SSH: Deleting file {$file} from remote host ", Zend_Log::INFO);
						if(!$this->deleteRemote($path . '/' . $fileData['file_name'])) {
							Billrun_Factory::log()->log("SSH: Failed to delete file: " . $file, Zend_Log::WARN);
						}
					}
				}
				// Check limit
				$this->limit = 1;	
				if ($count >= $this->limit) {
					break;
				}
				Billrun_Factory::dispatcher()->trigger('afterFileReceived', array($this, $file));
			}
		} catch (Exception $e) {
			Billrun_Factory::log()->log("SSH: Fail when downloading. with exception : " . $e, Zend_Log::DEBUG);
			return array();
		}

		return $ret;
	}
	
	/**
	 * copy the file to the location defined
	 * @since 5.0
	 */
	public function export($fileName){
		if (!empty($this->connection)){
			$local = $this->localDir . '/' . $fileName;
			$remote = $this->remoteDir . '/' . $fileName;
			if (!$this->connection->connected()) {
				Billrun_Factory::log()->log("Connecting the ssh server...", Zend_Log::DEBUG);
				$this->connection->connect($this->username);
				Billrun_Factory::log()->log("successfully connected to server", Zend_Log::DEBUG);
			} else {
				Billrun_Factory::log()->log("Already connected to ssh server, starting to export...", Zend_Log::DEBUG);
			}
			return $this->connection->put($local, $remote);
		}
		else {
			if ($this->move_exported) {
				$source = $this->localDir . '/' . $fileName;
				$dest = $this->remoteDir . '/' . $fileName;
				copy($source, $dest);
			}
		}
	}
	
	/**
	 * delete file from remote host
	 * @param String $file_path
	 * @return boolean
	 */
	protected function deleteRemote($file_path) {
		return $this->connection->deleteFile($file_path);
	}

	/**
	 * check if the received file was correctly received
	 * @param String $remoteFile
	 * @param String $localFilePath
	 */
	protected function isFileReceivedCorrectly($remoteFile, $localFilePath) {
		if ($this->checkReceivedSize) {
			$local_size = filesize($localFilePath);
			$remote_size = $this->connection->getFileSize($remoteFile);
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
	protected function isFileValid($filename) {
		return preg_match($this->filenameRegex, $filename);
	}
	
	/**
	 * gets a file timestamp
	 * @param String $file_path
	 */
	protected function getSourceTimestamp($file_path) {
		return $this->connection->getTimestamp($file_path);
	}

	protected function logDB($fileData) {
		Billrun_Factory::dispatcher()->trigger('beforeLogReceiveFile', array(&$fileData, $this));
		
		$query = array(
			'stamp' => $fileData['stamp'],
			'received_time' => array('$exists' => false)
		);

		$addData = array(
			'received_hostname' => Billrun_Util::getHostName(),
			'received_time' => new MongoDate()
		);

		$update = array(
			'$set' => array_merge($fileData, $addData)
		);

		if (empty($query['stamp'])) {
			Billrun_Factory::log("Billrun_Receiver::logDB - got file with empty stamp :  {$fileData['stamp']}", Zend_Log::NOTICE);
			return FALSE;
		}

		$log = Billrun_Factory::db()->logCollection();
		$result = $log->update($query, $update);

		if ($result['ok'] != 1 || $result['n'] != 1) {
			Billrun_Factory::log("Billrun_Receiver::logDB - Failed when trying to update a file log record " . $fileData['file_name'] . " with stamp of : {$fileData['stamp']}", Zend_Log::NOTICE);
		}

		return $result['n'] == 1 && $result['ok'] == 1;
	}
}
