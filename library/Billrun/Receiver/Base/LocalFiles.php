<?php
/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing Files receiver class
 *
 * @package  Billing
 * @since    1.0
 */
abstract class Billrun_Receiver_Base_LocalFiles extends Billrun_Receiver {

	/**
	 * The type of the object
	 *
	 * @var string
	 */
	static protected $type = 'files';

	/**
	 * the source directory to get the files from.
	 * @var type 
	 */
	protected $srcPath = null; 
	
	public function __construct($options) {
		parent::__construct($options);

		if (isset($options['workspace'])) {
			$this->workspace = $options['workspace'];
		} 
		
		if (isset($options['path'])) {
			$this->srcPath = $options['path'];
		} 
	}

	/**
	 * General function to receive
	 *
	 * @return array list of files received
	 */
	public function receive() {
		
			$this->dispatcher->trigger('beforeLocalFilesReceive', array($this));
		
			$type = static::$type;
			if (!file_exists($this->srcPath)) {
				$this->log->log("NOTICE : SKIPPING $type !!! directory " .$this->srcPath . " not found!!", Zend_Log::NOTICE);
				return FALSE;
			}
			$files = scandir($this->srcPath);
			$ret = array();
			foreach ($files as $file) {
				$path = $this->srcPath . DIRECTORY_SEPARATOR . $file;
				if(!$this->isFileValid($file, $path) || $this->isFileProcessed($file, $type) || is_dir($path) ) { 
					continue; 
				}
				$path = $this->handleFile($path, $file);
				if(!$path) {
					$this->log->log("NOTICE : Couldn't relocate file from  $path.", Zend_Log::NOTICE);
					continue; 
				}
				$this->logDB($path);
				$ret[] = $path;
			}

		$this->dispatcher->trigger('afterLocalFilesReceived', array($this, $ret));	
			
		return $ret;
	}
	
	/**
	 * Handle the file before receiving it (move it to appropiate position, exctract it..).
	 * @param type $path The original file poistion
	 */
	protected function handleFile($srcPath, $filename) {
		$this->dispatcher->trigger('handlingLocalFilesReceive', array($this, &$srcPath,$filename));
		return $srcPath;
	}
	
	/**
	 * Get the directory that the files should be stored in.
	 * @return the Base dirctory that the received files should be transfered to.
	 */
	protected function getDestBasePath() {
		return $this->workspace . DIRECTORY_SEPARATOR . static::$type;
	}
	
	/**
	 * Verify that the file is a valid file. 
	 * @return boolean false if the file name should not be received true if it should.
	 */
	protected function isFileValid($filename, $path) {
		return true;
	}
	
	/**
	 * method to check if the file already processed
	 */
	private function isFileProcessed($filename, $type) {
		$log = $this->db->getCollection(self::log_table);
		$resource = $log->query(array(	
				'$or' => array(
						array('type' => $type), 
						array('source' => $type), 
					),
				'$or' => array(
					array('file' => $filename),
					array('file_name' => $filename)
				)))->cursor()->limit(1);
		return $resource->count() > 0;
	}

}
