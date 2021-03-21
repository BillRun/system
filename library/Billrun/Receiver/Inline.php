<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Files receiver class
 *
 * @package  Billing
 * @since    0.5
 */
class Billrun_Receiver_Inline extends Billrun_Receiver {

	/**
	 * Type of object
	 *
	 * @var string
	 */
	static protected $type = 'inline';

	/**
	 * the content of the file to be written
	 * @var string
	 */
	protected $file_content = null;

	/**
	 * the content of the file to be written
	 * @var string
	 */
	protected $filename = null;

	public function __construct($options) {
		parent::__construct($options);

		if (isset($options['workspace'])) {
			$this->workspace = Billrun_Util::getBillRunSharedFolderPath($options['workspace']);
//			if (!file_exists($this->workspace)) {
//				mkdir($this->workspace, 0755, true);
//			}
		}

		if (isset($options['file_content'])) {
			$this->file_content = $options['file_content'];
		} else if (isset($options['receiver']['file_content'])) {
			$this->file_content = $options['receiver']['file_content'];
		}
		if (isset($options['file_name'])) {
			$this->filename = $options['file_name'];
		} else if (isset($options['receiver']['file_name'])) {
			$this->filename = $options['receiver']['file_name'];
		}
	}

	/**
	 * General function to receive
	 *
	 * @return array list of files received
	 */
	public function receive() {

		Billrun_Factory::dispatcher()->trigger('beforeInlineFilesReceive', array($this));

		$type = static::$type;
		if (empty($this->file_content)) {
			Billrun_Factory::log("Skipping $this->filename - it is empty!", Zend_Log::WARN);
			return FALSE;
		}
		$ret = array();
		Billrun_Factory::log("Billrun_Receiver_Inline::receive - handle file {$this->filename}", Zend_Log::DEBUG);
		$this->lockFileForReceive($this->filename, $type);
		$path = $this->handleFile();
		if (!$path) {
			Billrun_Factory::log("Couldn't write file $this->filename.", Zend_Log::ERR);
			return FALSE;
		}

		$fileData = $this->getFileLogData($this->filename, $type);
		$fileData['path'] = $path;
		if (!empty($this->backupPaths)) {
			$backedTo = $this->backup($fileData['path'], $file->filename, $this->backupPaths, FALSE, FALSE);
			Billrun_Factory::dispatcher()->trigger('beforeReceiverBackup', array($this, &$fileData['path']));
			$fileData['backed_to'] = $backedTo;
			Billrun_Factory::dispatcher()->trigger('afterReceiverBackup', array($this, &$fileData['path']));
		}
		$this->logDB($fileData);
		$ret[] = $fileData['path'];

		Billrun_Factory::dispatcher()->trigger('afterInlineFilesReceive', array($this, $ret));

		return $ret;
	}

	/**
	 * Get the directory that the files should be stored in.
	 * @return the Base dirctory that the received files should be transfered to.
	 */
	protected function getDestBasePath() {
		return $this->workspace . DIRECTORY_SEPARATOR . static::$type;
	}

	protected function handleFile() {
		$ret = FALSE;
		Billrun_Factory::dispatcher()->trigger('beforeInlineFileHandling', array($this));
		$newPath = $this->getDestBasePath();
		@mkdir($newPath, 0755, true);
		if (file_exists($newPath)) {
			$newPath .= DIRECTORY_SEPARATOR . $this->filename;
			$ret = file_put_contents($newPath, $this->file_content);
			Billrun_Factory::dispatcher()->trigger('afterInlineFileHandling', array($this, &$newPath, $ret));
		}
		return $ret === FALSE ? FALSE : $newPath;
	}

}
