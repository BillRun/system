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


	public function __construct($options) {
		parent::__construct($options);

		if (isset($options['workspace'])) {
			$this->workspace = $options['workspace'];
		} else {
			$this->workspace = $this->config->nrtrde->workspace;
		}
		
		$this->ftp = new Zend_Ftp($this->config->nrtrde->ftp['host'], $this->config->nrtrde->ftp['user'], $this->config->nrtrde->ftp['password']);
		
	}

	/**
	 * general function to receive
	 *
	 * @return mixed
	 */
	public function receive() {

		// get files from ftp server
		$this->download();
		// extract
		// send to process
	}

	protected function download() {
		$directory = $this->ftp->getCurrentDirectory();
		$contents = $directory->getContents();
		print_R($contents);die;
		// Alternatively with chaining
//		$contents = Zend_Ftp::connect($host, $username, $password)
//			->getCurrentDirectory()
//			->getContents()
//		;

		foreach ($contents as $content) {
			if ($content->isFile()) {
				echo 'File: ' . $content->name . '<br />';
			} else {
				echo 'Directory: ' . $content->name . '<br />';
			}
		}

		$file = $ftp->getFile('/foo/bar.txt');
		$file->saveToPath('data');
		
		$ftp->getCurrentDirectory()->changeDirectory('foo')->saveContentsToPath('data');
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
