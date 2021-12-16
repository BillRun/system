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
class Billrun_PaymentGateway_Connection_Ftp extends Billrun_PaymentGateway_Connection{

	protected static $type = 'ftp';
	protected $port = '21';
	protected $passive;
	protected $checkReceivedSize = true;
	protected $deleteReceived;

	public function __construct($options) {
		parent::__construct($options);
		$this->connection = Zend_Ftp::connect($this->host, $this->username, $this->password);
		$this->passive = isset($options['passive']) ? $options['passive'] : false;
		$this->source = isset($options['type']) ? $options['type'] : self::$type;
		$this->deleteReceived = !empty($options['delete_received']) ? true : false;
	}
	
	public function receive() {
		if (empty($this->connection)) {
			Billrun_Factory::log("Missing connection", Zend_Log::DEBUG);
			return false;
		}
		$ret = array();
		Billrun_Factory::dispatcher()->trigger('beforeFTPReceiveFullRun', array($this));
			$hostName = $this->host;

			if (is_numeric($hostName)) {
				$hostName = '';
			}
			if (!empty($this->remoteDir) && substr($this->remoteDir, -1) != DIRECTORY_SEPARATOR) {
				$this->remoteDir .= DIRECTORY_SEPARATOR;
			}
			
			Billrun_Factory::log()->log("Connecting to FTP server: " . $this->host, Zend_Log::INFO);
			$this->connection->setPassive(isset($this->passive) ? $this->passive : false);
			$this->connection->setMode(2); // setting ftp mode to binary
			
			Billrun_Factory::log()->log("Success: Connected to: " . $this->host, Zend_Log::INFO);
			$hostRet = array();
			Billrun_Factory::dispatcher()->trigger('beforeFTPReceive', array($this, $hostName));
			try {
				$hostRet = $this->receiveFromHost($hostName);
			} catch (Exception $e) {
				Billrun_Factory::log("FTP: Fail when downloading from : $hostName with exception : " . $e->getMessage(), Zend_Log::ALERT);
				return $ret;
			}
			Billrun_Factory::dispatcher()->trigger('afterFTPReceived', array($this, $hostRet, $hostName));

			$ret = array_merge($ret, $hostRet);
		

		Billrun_Factory::dispatcher()->trigger('afterFTPReceivedFullRun', array($this, $ret, $hostName));
		return $ret;
	}
	
	public function export($fileName) {
		if (!empty($this->connection)) {
			$local = $this->localDir . '/' . $fileName;
			$remote = $this->remoteDir . '/' . $fileName;
			$ftpResource = $this->connection->getConnection();
			ftp_put($ftpResource, $remote, $local, FTP_BINARY);
		}
	}
	
	/**
	 * Receive files from the ftp host.
	 * @param type $hostName the ftp hostname/alias
	 * @param type $config the ftp configuration
	 * @return array conatining the path to the received files.
	 */
	protected function receiveFromHost($hostName) {
		$ret = array();
		$files = $this->connection->getDirectory($this->remoteDir)->getContents();

		Billrun_Factory::log("FTP: Starting to receive from remote host : $hostName", Zend_Log::INFO);
		$count = 0;
		foreach ($this->sortByFileDate($files) as $file) {
			Billrun_Factory::dispatcher()->trigger('beforeFileReceive', array($this, &$file));
			Billrun_Factory::log("FTP: Found file " . $file->name . " on remote host", Zend_Log::INFO);
			$extraData = array();
			Billrun_Factory::dispatcher()->trigger('beforeFTPFileReceived', array(&$file, $this, $hostName, &$extraData));
			$isFileReceivedMoreFields = array('retrieved_from' => $hostName);
			if ($extraData) {
				$isFileReceivedMoreFields['extra_data'] = $extraData;
			}

			if (!$this->shouldFileBeReceived($file, $isFileReceivedMoreFields)) {
				continue;
			}
			$fileData = $this->getFileLogData($file->name, $this->source, $isFileReceivedMoreFields);

			Billrun_Factory::log("FTP: Download file " . $file->name . " from remote host", Zend_Log::INFO);
			$targetPath = $this->workspace;
			if (substr($targetPath, -1) != '/') {
				$targetPath .= '/';
			}
			$config = array($this->deleteReceived, $this->passive, $this->connection, $this->source);
			$targetPath.=date("Ym") . DIRECTORY_SEPARATOR . substr(md5(serialize($config)), 0, 7) . DIRECTORY_SEPARATOR;
			if (!file_exists($targetPath)) {
				mkdir($targetPath, 0777, true);
			}
			if ($file->saveToPath($targetPath, null, 0, true) === FALSE) { // the last arg declare try to recover on failure
				Billrun_Factory::log("FTP: failed to download " . $file->name . " from remote host", Zend_Log::ALERT);
				continue;
			}
			$fileData['path'] = $targetPath . $file->name;

			if (!$this->isFileReceivedCorrectly($file, $fileData['path'])) {
				continue;
			}
			if ($this->preserve_timestamps) {
				$timestamp = $file->getModificationTime();
				if ($timestamp !== FALSE) {
					Billrun_Util::setFileModificationTime($fileData['path'], $timestamp);
				}
			}
			Billrun_Factory::dispatcher()->trigger('afterFTPFileReceived', array(&$fileData['path'], $file, $this, $hostName, $extraData));

			if (!empty($this->backupPaths)) {
				$backedTo = $this->backup($fileData['path'], $file->name, $this->backupPaths, $hostName, FALSE);
				Billrun_Factory::dispatcher()->trigger('beforeReceiverBackup', array($this, &$fileData['path'], $hostName));
				$fileData['backed_to'] = $backedTo;
				Billrun_Factory::dispatcher()->trigger('afterReceiverBackup', array($this, &$fileData['path'], $hostName));
			}
			Billrun_Factory::dispatcher()->trigger('afterFileReceived', array($this, $file->name, $fileData));
			if ($this->logDB($fileData)) {
				$ret[] = $fileData['path'];
				$count++; //count the file as recieved
				// delete the file after downloading and store it to processing queue
				if ($this->deleteReceived) {
					Billrun_Factory::log("FTP: Deleting file {$file->name} from remote host ", Zend_Log::INFO);
					$file->delete();
				}
			}
			if ($count >= $this->limit) {
				break;
			}
		}
		return $ret;
	}
	
	/**
	 * Check if a remote file shold be received for further processing.
	 * @param type $file
	 */
	protected function shouldFileBeReceived($file, $isFileReceivedMoreFields) {
		$ret = true;
		if (!$file->isFile()) {
			Billrun_Factory::log("FTP: " . $file->name . " is not a file", Zend_Log::DEBUG);
			$ret = false;
		} else if (!$this->isFileValid($file->name, $file->path)) {
			Billrun_Factory::log("FTP: " . $file->name . " is not a valid file", Zend_Log::DEBUG);
			$ret = false;
		} else if (!$this->lockFileForReceive($file->name, $this->source, $isFileReceivedMoreFields)) {
			Billrun_Factory::log("FTP: " . $file->name . " received already", Zend_Log::INFO);
			$ret = false;
		}
		return $ret;
	}

	/**
	 * check if the received file was correctly received
	 * @param type $param
	 */
	protected function isFileReceivedCorrectly($remoteFile, $localFilePath) {
		if ($this->checkReceivedSize) {
			$local_size = filesize($localFilePath);
			$remote_size = $remoteFile->size();
			if ($local_size !== $remote_size) {
				Billrun_Factory::log("FTP: The remote file size (" . $remote_size . ") is different from local file size (" . $local_size . "). File name: " . $remoteFile->name, Zend_Log::ERR);
				return false;
			}
		}
		return true;
	}

	/**
	 * Sort an array of files returned by the ftp  by the  file date  and  file name
	 * @param type $files the ftp  directrory iterator
	 * @return type
	 */
	protected function sortByFileDate($files) {
		if (!is_array($files)) {
			$files = iterator_to_array($files);
		}
		usort($files, function ($a, $b) {
			if ($a->isFile() && $b->isFile() &&
				isset($a->extraData['date']) && isset($b->extraData['date'])) {
				return ($a->extraData['date'] - $b->extraData['date']) + (strcmp($a->name, $b->name) * 0.1);
			}

			return strcmp($a->name, $b->name);
		});

		return $files;
	}

	/**
	 * Verify that the file is a valid file. 
	 * @return boolean false if the file name should not be received true if it should.
	 */
	protected function isFileValid($filename, $path) {
		return preg_match($this->filenameRegex, $filename);
	}
	
	/**
	 * method to log the processing
	 * 
	 * @todo refactoring this method
	 */
	protected function logDB($fileData) {
		Billrun_Factory::dispatcher()->trigger('beforeLogReceiveFile', array(&$fileData, $this));
		
		$query = array(
			'stamp' => $fileData['stamp'],
			'received_time' => array('$exists' => false)
		);

		$addData = array(
			'received_hostname' => Billrun_Util::getHostName(),
			'received_time' => new Mongodloid_Date(),
			'pg_file_type' => $this->fileType
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