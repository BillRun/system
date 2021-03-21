<?php

/**
 * An NSN  CDR  file handling class.
 */
class Zend_Ftp_File_NsnCDRFile  extends Zend_Ftp_File  implements Zend_Ftp_File_IFile, ArrayAccess {
	
	protected $parentDir;
	
	public function __construct($path, $ftp, $extraData, $parentDir ) {
		parent::__construct($path, $ftp, $extraData);
		$this->parentDir = $parentDir;
	}
	
	/**
	 * Mark the file as processed (don't delete it)
	 * 
	 * @return Zend_Ftp_File
	 */
	public function delete() {
		if($this->parentDir) {
			$this->parentDir->markProcessed($this);
		}

		return $this;
	}

	public function offsetExists($offset) {
		return isset($this->_extraData[$offset]);
	}

	public function offsetGet($offset) {
		return $this->_extraData[$offset];
	}

	public function offsetSet($offset, $value) {
		return $this->_extraData[$offset] = $value;
	}

	public function offsetUnset($offset) {
		unset($this->_extraData[$offset]);
	}
}
