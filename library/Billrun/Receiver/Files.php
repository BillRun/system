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
class Billrun_Receiver_Files extends Billrun_Receiver {

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
			'path' => $filePath,
			'parser' => Billrun_Parser::getInstance(array('type' => 'fixed')),
			'db' => $this->db,
		);

		$processor = Billrun_Processor::getInstance($options);
		if ($processor) {
			$processor->process();
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
