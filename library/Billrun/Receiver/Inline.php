<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
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
			$this->workspace = $options['workspace'];
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
			Billrun_Factory::log()->log("NOTICE : SKIPPING $this->filename !!! It is empty!!!", Zend_Log::NOTICE);
			return FALSE;
		}
		$ret = array();
		Billrun_Factory::log()->log("Billrun_Receiver_Inline::receive - handle file {$this->filename}", Zend_Log::DEBUG);
		$path = $this->handleFile();
		if (!$path) {
			Billrun_Factory::log()->log("NOTICE : Couldn't write file $this->filename.", Zend_Log::NOTICE);
		} else {
			$this->logDB($path);
			$ret[] = $path;
		}

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
		Billrun_Factory::dispatcher()->trigger('beforeInlineFileHandling', array($this));
		$newPath = $this->getDestBasePath();
		if (!file_exists($newPath)) {
			mkdir($newPath, 0777, true);
		}
		$newPath .= DIRECTORY_SEPARATOR . $this->filename;
		$ret = file_put_contents($newPath, $this->file_content);
		Billrun_Factory::dispatcher()->trigger('afterInlineFileHandling', array($this, &$newPath, $ret));
		return $ret === FALSE ? FALSE : $newPath;
	}

}
