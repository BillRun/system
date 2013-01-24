<?php

class Billrun_Receiver_Relocate extends Billrun_Receiver_Base_LocalFiles {
	
	public function __construct($options) {
		parent::__construct($options);
	}
	
	/**
	 * Move the file to the workspace.
	 * @param type $path The original file poistion
	 */
	protected function handleFile($srcPath, $filename) {
		$srcPath = parent::handleFile($srcPath, $filename);
		$newPath = $this->workspace . DIRECTORY_SEPARATOR . static::$type;
		if(!file_exists($newPath)) {
			mkdir($newPath);
		}
		$newPath .= DIRECTORY_SEPARATOR . $filename;
		return copy($srcPath, $newPath) ? $newPath : FALSE;
	}
}