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
class Billrun_PaymentGateway_Connection_Relocate extends Billrun_PaymentGateway_Connection {

	protected static $type = 'relocate';
	protected $checkReceivedSize = true;
	protected $source;
	protected $payments_file_type;

	public function __construct($options) {
		parent::__construct($options);

		if (isset($options['workspace'])) {
			$this->workspace = Billrun_Util::getBillRunSharedFolderPath($options['workspace']);
		}

		if (isset($options['path'])) {
			$this->srcPath = Billrun_Util::getBillRunSharedFolderPath($options['path']);
		} else if (isset($options['receiver']['path'])) {
			$this->srcPath = Billrun_Util::getBillRunSharedFolderPath($options['receiver']['path']);
		}

		if (isset($options['receiver']['sort'])) {
			$this->sort = $options['receiver']['sort'];
		}

		if (isset($options['receiver']['order'])) {
			$this->order = $options['receiver']['order'];
		}


		$this->source = isset($options['type']) ? $options['type'] : self::$type;
		$this->payments_file_type = isset($options['payments_file_type']) ? $options['payments_file_type'] : "";
	}

	public function receive() {
		Billrun_Factory::dispatcher()->trigger('beforeLocalFilesReceive', array($this));

		$type = $this->source;
		if (!file_exists($this->srcPath)) {
			Billrun_Factory::log("Skipping $type. Directory " . $this->srcPath . " not found!", Zend_Log::ERR);
			return array();
		}
		$files = $this->getFiles($this->srcPath, $this->sort, $this->order);
		$ret = array();
		$receivedCount = 0;
		foreach ($files as $file) {
			$path = $this->srcPath . DIRECTORY_SEPARATOR . $file;
			if (!$this->isFileValid($file, $path) || is_dir($path)) {
				Billrun_Factory::log('File ' . $file . ' is not valid', Zend_Log::INFO);
				continue;
			}
			$moreFields = array();
			if (!empty($this->fileType)) {
				$moreFields['pg_file_type'] = $this->fileType;
				$moreFields['cpg_file_type'] = $this->fileType;
			}
			if (!empty($this->cpgName)) {
				$moreFields['cpg_name'] = $this->cpgName;
			}
			if (!$this->lockFileForReceive($file, $type, $moreFields)) {
				Billrun_Factory::log('File ' . $file . ' has been received already', Zend_Log::INFO);
				continue;
			}
			Billrun_Factory::log("Billrun_Receiver_Base_LocalFiles::receive - handle file {$file}", Zend_Log::DEBUG);

			$fileData = $this->getFileLogData($file, $type, $moreFields);
			$fileData['path'] = $this->handleFile($path, $file);

			if (!$fileData['path']) {
				Billrun_Factory::log("Couldn't relocate file from $path.", Zend_Log::NOTICE);
				continue;
			}
			if (!empty($this->backupPaths)) {
				$backedTo = $this->backup($fileData['path'], $file, $this->backupPaths, FALSE, FALSE);
				Billrun_Factory::dispatcher()->trigger('beforeReceiverBackup', array($this, &$fileData['path']));
				$fileData['backed_to'] = $backedTo;
				Billrun_Factory::dispatcher()->trigger('afterReceiverBackup', array($this, &$fileData['path']));
			}
			if ($this->logDB($fileData) !== FALSE) {
				$ret[] = $fileData['path'];

				if (( ++$receivedCount) >= $this->limit) {
					break;
				}
			}
		}

		Billrun_Factory::dispatcher()->trigger('afterLocalFilesReceived', array($this, $ret));

		return $ret;
	}

	/**
	 * get list of files in specific path
	 * 
	 * @param string $path
	 * @param string $sort you can sort by name, date or size, default: name
	 * @param string $order asc or desc. default: asc
	 * @return array list of file names
	 * @todo make defines (constants) for sort argument
	 * @todo move to utils
	 */
	protected function getFiles($path, $sort = 'name', $order = 'asc') {
		$files = array();
		switch ($sort) {
			case 'date':
			case 'time':
			case 'datetime':
			case 'size':
				if ($sort == 'size') {
					$callback = 'filesize';
				} else {
					$callback = 'filemtime';
				}
				if ($handle = opendir($path)) {
					while (false !== ($file = readdir($handle))) {
						if ($file != "." && $file != "..") {
							$key = call_user_func_array($callback, array($path . '/' . $file));
							if (isset($files[$key])) {
								$files[$key . $file] = $file;
							} else {
								$files[$key] = $file;
							}
						}
					}
					closedir($handle);
					// sort
					if ($order == 'desc') {
						krsort($files);
					} else {
						ksort($files);
					}
				}
				break;
			default:
				if ($order == 'desc') {
					$files = scandir($path, SCANDIR_SORT_DESCENDING);
				} else {
					$files = scandir($path);
				}
				break;
		}

		return array_values($files);
	}

	/**
	 * Move the file to the workspace.
	 * 
	 * 
	 * @return string the new path
	 */
	protected function handleFile($srcPath, $filename) {
		Billrun_Factory::dispatcher()->trigger('handlingLocalFilesReceive', array($this, &$srcPath, $filename));
		return $srcPath;
	}

	/**
	 * Get the directory that the files should be stored in.
	 * @return the Base dirctory that the received files should be transfered to.
	 */
	protected function getDestBasePath() {
		return $this->workspace . DIRECTORY_SEPARATOR . static::$type;
	}

	protected function logDB($fileData) {
		Billrun_Factory::dispatcher()->trigger('beforeLogReceiveFile', array(&$fileData, $this));

		$query = array(
			'stamp' => $fileData['stamp'],
			'received_time' => array('$exists' => false)
		);

		$addData = array(
			'received_hostname' => Billrun_Util::getHostName(),
			'received_time' => new Mongodloid_Date(),
			'payments_file_type' => $this->payments_file_type,
			'type' => 'custom_payment_gateway'
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

	public function export($fileName) {
		
	}

}
