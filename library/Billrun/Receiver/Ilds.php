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

	public function __construct($options) {
		parent::__construct($options);
		
		if (isset($options['workspace'])) {
			$this->workspace = $options['workspace'];
		} else {
			$this->workspace = $this->config->ilds->path;
		}
	}

	/**
	 * general function to receive
	 *
	 * @return mixed
	 */
	public function receive() {

		foreach ($this->config->providers->toArray() as $type) {
			if (!file_exists($this->workspace . DIRECTORY_SEPARATOR . $type)) {
				print("NOTICE : SKIPPING $type !!! directory " . $this->workspace . DIRECTORY_SEPARATOR . $type . " not found!!");
				continue;
			}
			$files = scandir($this->workspace . DIRECTORY_SEPARATOR . $type);
			foreach ($files as $file) {
				$path = $this->workspace . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . $file;
				if (is_dir($path) || $this->isFileProcessed($file, $type)) {
					continue;
				}

				$this->processFile($path, $type);
			}
		}
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
			$processor->process();
		} else {
			$this->log->log("error with loading processor", Zend_log::ERR);
			return false;
		}

		$data = $processor->getData();

		$this->log->log("Process type: " . $type, Zend_log::INFO);
		$this->log->log("file path: " . $filePath, Zend_log::INFO);
		$this->log->log((isset($data['data']) ? "import lines: " . count($data['data']) : "no data received"), Zend_log::INFO);
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
