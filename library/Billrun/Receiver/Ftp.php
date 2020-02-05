<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing receiver for ftp
 *
 * @package  Billing
 * @since    0.5
 */
class Billrun_Receiver_Ftp extends Billrun_Receiver {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'ftp';

	/**
	 * resource to the ftp server
	 * 
	 * @var Zend_Ftp
	 */
	protected $ftp = null;

	/**
	 * the path on the remote server
	 * 
	 * @param string
	 */
	protected $ftp_path = '/';
	protected $ftpConfig = false;
	
	/**
	 * the time which after can delete files from the ftp server.
	 * 
	 * @param string
	 */
	protected $file_delete_orphan_time;
	
	/**
	 * if true delete files after fixed orphan time. 
	 * 
	 * @param string
	 */
	protected $delete_old_files;

	protected $checkReceivedSize = true;
	public function __construct($options) {
		parent::__construct($options);
		$this->ftpConfig = isset($options['ftp']['host']) ? array($options['ftp']) : $options['ftp'];

		if (isset($options['ftp']['remote_directory'])) {
			$this->ftp_path = $options['ftp']['remote_directory'];
		}

		if (isset($options['workspace'])) {
			$this->workspace = $options['workspace'];
		}

		if (isset($options['received']['check_received_size'])) {
			$this->checkReceivedSize = $options['received']['check_received_size'];
		}
		
		if (isset($options['receiver']['orphan_delete_time'])){
			$this->file_delete_orphan_time = $options['receiver']['orphan_delete_time'];
		}
		
		if (isset($options['delete']['old_files'])){
			$this->delete_old_files = $options['delete']['old_files'];
		}

		Zend_Ftp_Factory::registerParserType(Zend_Ftp::UNKNOWN_SYSTEM_TYPE, 'Zend_Ftp_Parser_NsnFtpParser');
		Zend_Ftp_Factory::registerInteratorType(Zend_Ftp::UNKNOWN_SYSTEM_TYPE, 'Zend_Ftp_Directory_NsnIterator');
		Zend_Ftp_Factory::registerFileType(Zend_Ftp::UNKNOWN_SYSTEM_TYPE, 'Zend_Ftp_File_NsnCDRFile');
		Zend_Ftp_Factory::registerDirecotryType(Zend_Ftp::UNKNOWN_SYSTEM_TYPE, 'Zend_Ftp_Directory_Nsn');

	}

	/**
	 * method to receive files through ftp
	 * 
	 * @return array list of the files received
	 */
	public function receive() {
		$ret = array();
		Billrun_Factory::dispatcher()->trigger('beforeFTPReceiveFullRun', array($this));

		foreach ($this->ftpConfig as $hostName => $config) {
			if (!is_array($config)) {
				continue;
			}

			if (is_numeric($hostName)) {
				$hostName = '';
			}

			if(!empty($config['server_type_override']) ) {
				$fullServerType = defined("Zend_Ftp::{$config['server_type_override']['server_type']}") ?
									"Zend_Ftp::{$config['server_type_override']['server_type']}" :
									$config['server_type_override']['server_type'] ;
				if(defined($fullServerType)) {
					$resolvedServerType = constant($fullServerType);
					Zend_Ftp_Factory::registerParserType($resolvedServerType, Billrun_Util::getFieldVal($config['server_type_override']['parser'],'Zend_Ftp_Parser_Unknown'));
					Zend_Ftp_Factory::registerInteratorType($resolvedServerType, Billrun_Util::getFieldVal($config['server_type_override']['iterator'],'Zend_Ftp_Directory_Unknown'));
					Zend_Ftp_Factory::registerFileType($resolvedServerType, Billrun_Util::getFieldVal($config['server_type_override']['file'],'Zend_Ftp_File'));
					Zend_Ftp_Factory::registerDirecotryType($resolvedServerType, Billrun_Util::getFieldVal($config['server_type_override']['directory'],'Zend_Ftp_Directory'));
				} else {
					Billrun_Factory::log("Couldn't identify FTP server type: {$fullServerType}",Zend_Log::ERR);
				}
			}
			$this->ftp = Zend_Ftp::connect($config['host'], $config['user'], $config['password']);
			$this->ftp->setPassive(isset($config['passive']) ? $config['passive'] : false);

			Billrun_Factory::dispatcher()->trigger('beforeFTPReceive', array($this, $hostName));
			try {
				$hostRet = $this->receiveFromHost($hostName, $config);
			} catch (Exception $e) {
				Billrun_Factory::log()->log("FTP: Fail when downloading from : $hostName with exception : " . $e, Zend_Log::WARN);
			}
			Billrun_Factory::dispatcher()->trigger('afterFTPReceived', array($this, $hostRet, $hostName));

			$ret = array_merge($ret, $hostRet);
		}

		Billrun_Factory::dispatcher()->trigger('afterFTPReceivedFullRun', array($this, $ret, $hostName));
		return $ret;
	}

	/**
	 * Receive files from the ftp host.
	 * @param type $hostName the ftp hostname/alias
	 * @param type $config the ftp configuration
	 * @return array conatining the path to the received files.
	 */
	protected function receiveFromHost($hostName, $config) {
		$ret = array();
		$files = $this->ftp->getDirectory($config['remote_directory'])->getContents();

		Billrun_Factory::log()->log("FTP: Starting to receive from remote host : $hostName", Zend_Log::INFO);
		$count = 0;
		foreach ($this->sortByFileDate($files) as $file) {
			Billrun_Factory::log()->log("FTP: Found file " . $file->name . " on remote host", Zend_Log::INFO);
			$extraData = array();
			Billrun_Factory::dispatcher()->trigger('beforeFTPFileReceived', array(&$file, $this, $hostName, &$extraData));
			$isFileReceivedMoreFields = array('retrieved_from' => $hostName);
			if ($extraData) {
				$isFileReceivedMoreFields['extra_data'] = $extraData;
			}

			if(!$this->shouldFileBeReceived($file, $isFileReceivedMoreFields) ) {
				if ($this->isLongTimeSinceReceive($file->name, static::$type, $isFileReceivedMoreFields)) {
					Billrun_Log::getInstance()->log("Deleting old file " . $file->name, Zend_log::NOTICE);
					$file->delete();//delete file
				}		
				continue;	
			}
			
			$fileData = $this->getFileLogData($file->name, static::$type, $isFileReceivedMoreFields);

			Billrun_Factory::log()->log("FTP: Download file " . $file->name . " from remote host", Zend_Log::INFO);
			$targetPath = $this->workspace;
			if (substr($targetPath, -1) != '/') {
				$targetPath .= '/';
			}
			$targetPath.=date("Ym") . DIRECTORY_SEPARATOR . substr(md5(serialize($config)), 0, 7) . DIRECTORY_SEPARATOR;
			if (!file_exists($targetPath)) {
				mkdir($targetPath, 0777, true);
			}
			if ($file->saveToPath($targetPath, null, 0, true) === FALSE) { // the last arg declare try to recover on failure
				Billrun_Factory::log()->log("FTP: failed to download " . $file->name . " from remote host", Zend_Log::ALERT);
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

			if(!empty($this->backupPaths)) {
				$backedTo = $this->backup($fileData['path'], $file->name, $this->backupPaths, $hostName, FALSE);
				Billrun_Factory::dispatcher()->trigger('beforeReceiverBackup', array($this, &$fileData['path'], $hostName));
				$fileData['backed_to'] = $backedTo;
				Billrun_Factory::dispatcher()->trigger('afterReceiverBackup', array($this, &$fileData['path'], $hostName));
			}
			if ($this->logDB($fileData)) {				
				$ret[] = $fileData['path'];
				$count++; //count the file as recieved
				// delete the file after downloading and store it to processing queue
				if (Billrun_Factory::config()->isProd() && (isset($config['delete_received']) && $config['delete_received'] )) {
					Billrun_Factory::log()->log("FTP: Deleting file {$file->name} from remote host ", Zend_Log::INFO);
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
			Billrun_Factory::log()->log("FTP: " . $file->name . " is not a file", Zend_Log::INFO);
			$ret = false;
		}else if (!$this->isFileValid($file->name, $file->path)) {
			Billrun_Factory::log()->log("FTP: " . $file->name . " is not a valid file", Zend_Log::INFO);
			$ret = false;
		} else if (!$this->lockFileForReceive($file->name, static::$type, $isFileReceivedMoreFields)) {
			Billrun_Factory::log()->log("FTP: " . $file->name . " received already", Zend_Log::INFO);
			$ret = false;
		}
		return $ret;
	}

	/**
	 * check if the received file was correctly received
	 * @param type $param
	 */
	protected function isFileReceivedCorrectly($remoteFile,$localFilePath) {
		if($this->checkReceivedSize) {
			$local_size = filesize($localFilePath);
			$remote_size = $remoteFile->size();
			if ($local_size !== $remote_size) {
				Billrun_Factory::log()->log("FTP: The remote file size (" . $remote_size . ") is different from local file size (" . $local_size . "). File name: " . $remoteFile->name, Zend_Log::ERR);
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
	
	protected function isLongTimeSinceReceive($filename, $type, $more_fields = array()){
		if ($this->delete_old_files != TRUE) {
			return FALSE;
		}
		$log = Billrun_Factory::db()->logCollection();
		$orphan_window = $this->file_delete_orphan_time;
		if (empty($orphan_window)) {
			return FALSE;
		}
		$logData = $this->getFileLogData($filename, $type, $more_fields);
		$orphan_time = date("Y-m-d H:i:s", time() - $orphan_window);
		$query = array(
			'stamp' => $logData['stamp'],
			'file_name' => $filename,
			'source' => 'nsn',
			'received_time' => array('$lt' => $orphan_time),
			'process_time' => array('$exists' => 1)
		);

		$result = $log->query($query)->cursor()->current();	
		if (empty($result) || $result->isEmpty()){
			return FALSE;
		}
		
		return TRUE;
	}
}
