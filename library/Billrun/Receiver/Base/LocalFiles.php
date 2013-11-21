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
abstract class Billrun_Receiver_Base_LocalFiles extends Billrun_Receiver {

	/**
	 * Type of object
	 *
	 * @var string
	 */
	static protected $type = 'localfiles';

	/**
	 * the source directory to get the files from.
	 * @var type 
	 */
	protected $srcPath = null;

	/**
	 * sort of the file receiving (name, date or size)
	 * 
	 * @var string
	 */
	protected $sort = 'name';

	/**
	 * order of the file receiving (asc or desc)
	 * @var string
	 */
	protected $order = 'asc';

	public function __construct($options) {
		parent::__construct($options);

		if (isset($options['workspace'])) {
			$this->workspace = $options['workspace'];
		}

		if (isset($options['path'])) {
			$this->srcPath = $options['path'];
		} else if (isset($options['receiver']['path'])) {
			$this->srcPath = $options['receiver']['path'];
		}

		if (isset($options['receiver']['sort'])) {
			$this->sort = $options['receiver']['sort'];
		}

		if (isset($options['receiver']['order'])) {
			$this->order = $options['receiver']['order'];
		}
	}

	/**
	 * General function to receive
	 *
	 * @return array list of files received
	 */
	public function receive() {

		Billrun_Factory::dispatcher()->trigger('beforeLocalFilesReceive', array($this));

		$type = static::$type;
		if (!file_exists($this->srcPath)) {
			Billrun_Factory::log()->log("NOTICE : SKIPPING $type !!! directory " . $this->srcPath . " not found!!", Zend_Log::NOTICE);
			return array();
		}
		$files = $this->getFiles($this->srcPath, $this->sort, $this->order);
		$ret = array();
		$receivedCount = 0;
		foreach ($files as $file) {
			$path = $this->srcPath . DIRECTORY_SEPARATOR . $file;
			Billrun_Factory::log('Check if file ' . $file . ' is valid and not yet received', Zend_Log::INFO);
			if (!$this->isFileValid($file, $path) || $this->isFileReceived($file, $type) || is_dir($path)) {
				continue;
			}
			Billrun_Factory::log()->log("Billrun_Receiver_Base_LocalFiles::receive - handle file {$file}", Zend_Log::DEBUG);
			$path = $this->handleFile($path, $file);
			if (!$path) {
				Billrun_Factory::log()->log("NOTICE : Couldn't relocate file from  $path.", Zend_Log::NOTICE);
				continue;
			}

			if ($this->logDB($path) !== FALSE) {
				$ret[] = $path;

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
							$files[$key] = $file;
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

}
