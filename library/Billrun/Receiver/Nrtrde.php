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
class Billrun_Receiver_Nrtrde extends Billrun_Receiver {
	
	/**
	 * resource to the ftp server
	 * 
	 * @var Zend_Ftp
	 */
	protected $ftp = null;
	
	/**
	 * the path on the remote server
	 * 
	 * @param string
	 */
	protected $ftp_path = '.';

	/**
	 * the path on the local machine that we will extract and import
	 * 
	 * @param string
	 */
	protected $workspace_path = '.';

	/**
	 * the path on the backup
	 * 
	 * @param string
	 */
	protected $backup_path = '.';
	
	public function __construct($options) {
		parent::__construct($options);

		$this->ftp = Zend_Ftp::connect($this->config->nrtrde->ftp['host'], $this->config->nrtrde->ftp['user'], $this->config->nrtrde->ftp['password']);
		$this->ftp->setPassive(false);
		
		if (isset($options['remote_directory'])) {
			$this->ftp_path = $options['remote_directory'];
		} else if (isset($this->config->nrtrde->ftp['remote_directory'])) {
			$this->ftp_path = $this->config->nrtrde->ftp['remote_directory'];
		}

		if (isset($options['workspace'])) {
			$this->workspace_path = $options['workspace'];
		} else if (isset($this->config->nrtrde->workspace)) {
			$this->workspace_path = $this->config->nrtrde->workspace;
		}

		if (isset($options['backup'])) {
			$this->backup_path = $options['backup'];
		} else if (isset($this->config->nrtrde->backup)) {
			$this->backup_path = $this->config->nrtrde->backup;
		}

	}

	/**
	 * general function to receive
	 *
	 * @return mixed
	 */
	public function receive() {

		// get files from ftp server
		$this->download();
		// extract the downloaded file (zip)
//		$this->extract('zip');
		// send to process
//		$this->processFile($filePath, $type);
	}
	
	protected function extract($type = 'zip') {
		
	}

	protected function download() {

		$files = $this->ftp->getDirectory($this->ftp_path)->getContents();

		$ret = array();
		foreach ($files as $file) {
			if ($file->isFile()) {
				$this->log->log("NRTRDE: Download file " . $file->name . " from remote host to ", Zend_Log::INFO);
				$file->saveToPath($this->workspace_path);
				$ret[] = $this->workspace_path . $file->name;
			}
		}

//		$file = $this->ftp->getFile('/foo/bar.txt');
//		$file->saveToPath('data');
//		$ftp->getCurrentDirectory()->changeDirectory('foo')->saveContentsToPath('data');
	}

	/**
	 * Process an ILD file
	 * @param $filePath  Path to the filethat needs processing.
	 * @param $type  the type of the ILD.
	 */
	private function processFile($filePath, $type) {

		$options = array(
			'type' => $type,
			'file_path' => $filePath,
			'parser' => parser::getInstance(array('type' => 'fixed')),
			'db' => $this->db,
		);

		$processor = processor::getInstance($options);
		if ($processor) {
			$ret = $processor->process();
		} else {
			echo "error with loading processor" . PHP_EOL;
		}

		$data = $processor->getData();
		//print result
		print "type: " . $type . PHP_EOL
			. "file path: " . $filePath . PHP_EOL
			. (isset($data['data']) ? "import lines: " . count($data['data']) : "no data received") . PHP_EOL;
	}

	/**
	 * method to check if the file already processed
	 */
	private function isFileProcessed($filename, $type) {
		$log = $this->db->getCollection(self::log_table);
		$resource = $log->query()->equals('type', $type)->equals('file', $filename);
		return $resource->count() > 0;
	}

}
