<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing relocate receiver class
 *
 * @package  Billing
 * @since    1.0
 */
class Billrun_Receiver_Relocate extends Billrun_Receiver_Base_LocalFiles {

	/**
	 * Type of object
	 *
	 * @var string
	 */
	static protected $type = 'relocate';
	
	/**
	 * Move the file to the workspace.
	 * 
	 * @param string $srcPath The original file position
	 * @param string $filename the filename
	 * 
	 * @return mixed the new path if success, else false
	 */
	protected function handleFile($srcPath, $filename) {
		$srcPath = parent::handleFile($srcPath, $filename);
		$this->dispatcher->trigger('beforeRelocateFileHandling', array($this, &$srcPath, $filename));
		$newPath = $this->workspace . DIRECTORY_SEPARATOR . static::$type;
		if (!file_exists($newPath)) {
			mkdir($newPath);
		}
		$newPath .= DIRECTORY_SEPARATOR . $filename;
		$ret = copy($srcPath, $newPath) ? $newPath : FALSE;
		$this->dispatcher->trigger('afterRelocateFileHandling', array($this, &$srcPath, &$newPath, $filename, $ret));
		return $ret;
	}

}