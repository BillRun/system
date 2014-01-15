<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
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

	public function __construct($options) {
		parent::__construct($options);
		$this->ftpConfig = isset($options['ftp']['host']) ? array($options['ftp']) : $options['ftp'];

		if (isset($options['ftp']['remote_directory'])) {
			$this->ftp_path = $options['ftp']['remote_directory'];
		}

		if (isset($options['workspace'])) {
			$this->workspace = $options['workspace'];
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

			$this->ftp = Zend_Ftp::connect($config['host'], $config['user'], $config['password']);
			$this->ftp->setPassive(isset($config['passive']) ? $config['passive'] : false);

			Billrun_Factory::dispatcher()->trigger('beforeFTPReceive', array($this, $hostName));
			try {
				$hostRet = $this->receiveFromHost($hostName, $config);
			} catch (Exception $e) {
				Billrun_Factory::log()->log("FTP: Fail when downloading from : $hostName with exception : " . $e, Zend_Log::DEBUG);
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

		Billrun_Factory::log()->log("FTP: Starting to receive from remote host : $hostName", Zend_Log::DEBUG);
		$count = 0;
		foreach ($this->sortByFileDate($files) as $file) {
			Billrun_Factory::log()->log("FTP: Found file " . $file->name . " on remote host", Zend_Log::DEBUG);
			$extraData = array();
			Billrun_Factory::dispatcher()->trigger('beforeFTPFileReceived', array(&$file, $this, $hostName, &$extraData));
			$isFileReceivedMoreFields = array('retrieved_from' => $hostName);
			if ($extraData) {
				$isFileReceivedMoreFields['extra_data'] = $extraData;
			}

			if (!$file->isFile()) {
				Billrun_Factory::log()->log("FTP: " . $file->name . " is not a file", Zend_Log::DEBUG);
				continue;
			}

			if (!$this->isFileValid($file->name, $file->path)) {
				Billrun_Factory::log()->log("FTP: " . $file->name . " is not a valid file", Zend_Log::DEBUG);
				continue;
			}

			if ($this->isFileReceived($file->name, static::$type, $isFileReceivedMoreFields)) {
				Billrun_Factory::log()->log("FTP: " . $file->name . " received already", Zend_Log::DEBUG);
				continue;
			}

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
			$received_path = $targetPath . $file->name;
			if ($this->preserve_timestamps) {
				$timestamp = $file->getModificationTime();
				if ($timestamp !== FALSE) {
					Billrun_Util::setFileModificationTime($received_path, $timestamp);
				}
			}
			Billrun_Factory::dispatcher()->trigger('afterFTPFileReceived', array(&$received_path, $file, $this, $hostName, $extraData));

			if ($this->logDB($received_path, $hostName, $extraData)) {
				$ret[] = $received_path;
				$count++; //count the file as recieved
				// delete the file after downloading and store it to processing queue
				if (Billrun_Factory::config()->isProd() && (isset($config['delete_received']) && $config['delete_received'] )) {
					Billrun_Factory::log()->log("FTP: Deleting file {$file->name} from remote host ", Zend_Log::DEBUG);
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

}
