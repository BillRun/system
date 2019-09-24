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
class Billrun_Receiver_Relocate extends Billrun_Receiver_Base_LocalFiles {

	/**
	 * Type of object
	 *
	 * @var string
	 */
	static protected $type = 'relocate';

	/**
	 * Flag to check if the relocate should moved the received files or copy them.
	 * @var boolean defaults to FALSE
	 */
	protected $moveFiles = false;

	public function __construct($options) {
		parent::__construct($options);

		if (isset($options['receiver']['move_received_files'])) {
			$this->moveFiles = $options['receiver']['move_received_files'];
			$this->noBackup = $this->noBackup && (!$this->moveFiles || $this->forceNoBackup);
		}
	}

	/**
	 * Move the file to the workspace.
	 * 
	 * @param string $srcPath The original file position
	 * @param string $filename the filename
	 * 
	 * @return mixed the new path if success, else false
	 */
	protected function handleFile($srcPath, $filename) {
		Billrun_Factory::log('Relocate receive handle file ' . $filename, Zend_Log::INFO);
		$srcPath = parent::handleFile($srcPath, $filename);
		Billrun_Factory::dispatcher()->trigger('beforeRelocateFileHandling', array($this, &$srcPath, $filename));
		$newPath = $this->workspace . DIRECTORY_SEPARATOR . static::$type;
		if (!file_exists($newPath)) {
			mkdir($newPath, 0777, true);
		}
		$newPath .= DIRECTORY_SEPARATOR . $filename;
		$ret = $this->moveFiles ? (copy($srcPath, $newPath) && unlink($srcPath)) : copy($srcPath, $newPath);
		if ($this->preserve_timestamps) {
			$timestamp = filemtime($srcPath);
			Billrun_Util::setFileModificationTime($newPath, $timestamp);
		}
		Billrun_Factory::dispatcher()->trigger('afterRelocateFileHandling', array($this, &$srcPath, &$newPath, $filename, $ret));
		return $ret ? $newPath : FALSE;
	}

}
