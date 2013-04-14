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
 * @since    0.5
 * @deprecated since version 0.2; use Billrun_Receiver_Relocate
 */
class Billrun_Receiver_Files extends Billrun_Receiver {

	/**
	 * Type of object
	 *
	 * @var string
	 */
	static protected $type = 'ilds';

	public function __construct($options) {
		parent::__construct($options);

		if (isset($options['workspace'])) {
			$this->workspace = $options['workspace'];
		} else {
			$this->workspace = Billrun_Factory::config()->getConfigValue('ilds.workspace', './workspace/');
		}
	}

	/**
	 * General function to receive
	 *
	 * @return array list of files received
	 */
	public function receive() {

		foreach (Billrun_Factory::config()->getConfigValue('ilds.providers', array()) as $type) {
			if (!file_exists($this->workspace . DIRECTORY_SEPARATOR . $type)) {
				Billrun_Factory::log()->log("NOTICE : SKIPPING $type !!! directory " . $this->workspace . DIRECTORY_SEPARATOR . $type . " not found!!", Zend_Log::NOTICE);
				continue;
			}

			$files = scandir($this->workspace . DIRECTORY_SEPARATOR . $type);
			$ret = array();
			static::$type = $type;
			foreach ($files as $file) {
				$path = $this->workspace . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . $file;
				if (is_dir($path) || $this->isFileReceived($file, $type) || !$this->isFileValid($file, $path)) {
					continue;
				}

				$this->logDB($path);
				$ret[] = $path;
			}
			$this->processType($type);
		}
		return $ret;
	}

	/**
	 * Process an ILD file
	 * @param $filePath  Path to the filethat needs processing.
	 * @param $type  the type of the ILD.
	 */
	private function processType($type) {

		$options = array(
			'type' => $type,
			//'path' => $filePath,
			'parser' => 'fixed',
		);

		$processor = Billrun_Processor::getInstance($options);
		if ($processor) {
			$processor->process_files();
		} else {
			Billrun_Factory::log()->log("error with loading processor", Zend_log::ERR);
			return false;
		}

		$data = $processor->getData();

		Billrun_Factory::log()->log("Process type: " . $type, Zend_log::INFO);
		//	Billrun_Factory::log()->log("file path: " . $filePath, Zend_log::INFO);
		Billrun_Factory::log()->log((isset($data['data']) ? "import lines: " . count($data['data']) : "no data received"), Zend_log::INFO);
	}

	/**
	 * method to check if the file already processed
	 */
	protected function isFileReceived($filename, $type) {
		$log = Billrun_Factory::db()->logCollection();
		$resource = $log->query()->equals('type', $type)->equals('file_name', $filename);
		return $resource->count() > 0;
	}

}
