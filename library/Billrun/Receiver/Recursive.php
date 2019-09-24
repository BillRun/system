<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing relocate receiver class
 *
 * @package  Billing
 * @since    0.5
 */
class Billrun_Receiver_Recursive extends Billrun_Receiver_Relocate {

	/**
	 * Type of object
	 *
	 * @var string
	 */
	static protected $type = 'recursive';


	protected $depth = 2;

	protected 	$receivedCount = 0;
	/**
	 * Flag to check if the relocate should moved the received files or copy them.
	 * @var boolean defaults to FALSE
	 */
	protected $moveFiles = false;

	public function __construct($options) {
		parent::__construct($options);
		if(!is_array($this->srcPath)) {
			$this->srcPath = [$this->srcPath];
		}

		$this->depth = Billrun_Util::getFieldVal($options['depth'],Billrun_Util::getFieldVal($options['receiver']['depth'],$this->depth));
	}
/**
	 * General function to receive
	 *
	 * @return array list of files received
	 */
	public function receive() {

		Billrun_Factory::dispatcher()->trigger('beforeLocalFilesReceive', array($this));
		$ret = array();
		$type = static::$type;
		$this->receivedCount=0;
		$ret = $this->receiveRecursive($this->srcPath,$type);
		Billrun_Factory::dispatcher()->trigger('afterLocalFilesReceived', array($this, $ret));

		return $ret;
	}

	protected function receiveRecursive($paths, $type, $depth = 0) {
		$ret = [];

		if($this->order == 'desc') {
			$paths = array_reverse($paths);
		}

		foreach($paths as $dirPath) {

			if (!file_exists($dirPath) ) {
				if(!empty($resolvedDirPaths = glob($dirPath))) {//Support for glob escaped paths
					$ret = array_merge($ret,$this->receiveRecursive($resolvedDirPaths, $type, $depth));
				} else {
					Billrun_Factory::log()->log("NOTICE : SKIPPING $type !!! directory " . $dirPath . " not found!!", Zend_Log::NOTICE);
				}
				continue;
			}
			$files = $this->getFiles($dirPath, $this->sort, $this->order);
			$files = array_filter($files, function($i) { return $i != '.' && $i != '..';});

			foreach ($files as $file) {
				$path = $dirPath . DIRECTORY_SEPARATOR . $file;
				if ($this->receivedCount >= $this->limit) {
					break 2;
				}
				if(is_dir($path) && $this->depth > $depth) {
					$ret = array_merge($ret,$this->receiveRecursive([$path], $type, $depth+1));
					continue;
				}

				if (!$this->isFileValid($file, $path) || is_dir($path)) {
					Billrun_Factory::log('File ' . $file . ' is not valid', Zend_Log::INFO);
					continue;
				}
				if($fileData = $this->receiveFile($file ,$type, $path) === FALSE) {
					continue;
				}

				$ret[] = $fileData;
				if (( ++$this->receivedCount) >= $this->limit) {
					break 2;
				}
			}
		}
		return $ret;
	}

	protected function receiveFile($file, $type, $path) {

		$extraData = [];
		Billrun_Factory::dispatcher()->trigger('beforeLocalFileReceived', array(&$path, $this, FALSE, &$extraData));

		if ( !$this->lockFileForReceive($file, $type, $extraData) ) {
			Billrun_Factory::log('File ' . $file . ' has been received already', Zend_Log::INFO);
			return FALSE;
		}
		Billrun_Factory::log()->log("Billrun_Receiver_Base_LocalFiles::receive - handle file {$file}", Zend_Log::DEBUG);


		$fileData = $this->getFileLogData($file, $type, $extraData);

		$fileData['path'] = $this->handleFile($path, $file);

		if (!$fileData['path']) {
			Billrun_Factory::log()->log("NOTICE : Couldn't relocate file from  $path.", Zend_Log::NOTICE);
			return FALSE;
		}
		if(!empty($this->backupPaths) && !$this->noBackup) {
			$backedTo = $this->backup($fileData['path'], $file, $this->backupPaths, FALSE, FALSE);
			Billrun_Factory::dispatcher()->trigger('beforeReceiverBackup', array($this, &$fileData['path']));
			$fileData['backed_to'] = $backedTo;
			Billrun_Factory::dispatcher()->trigger('afterReceiverBackup', array($this, &$fileData['path']));
		}
		if ($this->logDB($fileData) !== FALSE) {
			return $fileData['path'];


		}
		return FALSE;
	}

}
