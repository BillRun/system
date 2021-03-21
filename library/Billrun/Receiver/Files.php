<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
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
			$this->workspace = Billrun_Util::getBillRunSharedFolderPath($options['workspace']);
		} else {
			$this->workspace = Billrun_Util::getBillRunSharedFolderPath(Billrun_Factory::config()->getConfigValue('ilds.workspace', './workspace/'));
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
				Billrun_Factory::log("Skipping $type. Directory " . $this->workspace . DIRECTORY_SEPARATOR . $type . " not found!", Zend_Log::ERR);
				continue;
			}

			$files = scandir($this->workspace . DIRECTORY_SEPARATOR . $type);
			$ret = array();
			static::$type = $type;
			foreach ($files as $file) {
				$path = $this->workspace . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . $file;
				if (is_dir($path) || $this->lockFileForReceive($file, $type) || !$this->isFileValid($file, $path)) {
					continue;
				}
				$fileData = $this->getFileLogData($file, $type);
				$fileData['path'] = $path;
				if (!empty($this->backupPaths)) {
					$backedTo = $this->backup($fileData['path'], $file, $this->backupPaths, FALSE, FALSE);
					Billrun_Factory::dispatcher()->trigger('beforeReceiverBackup', array($this, &$fileData['path']));
					$fileData['backed_to'] = $backedTo;
					Billrun_Factory::dispatcher()->trigger('afterReceiverBackup', array($this, &$fileData['path']));
				}
				$this->logDB($fileData);
				$ret[] = $fileData['path'];
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
		if (!$processor) {
			Billrun_Factory::log("error with loading processor", Zend_Log::ERR);
			return false;
		}

		$processor->process_files();
		$data = $processor->getData();

		Billrun_Factory::log("Process type: " . $type, Zend_Log::INFO);
		//	Billrun_Factory::log("file path: " . $filePath, Zend_Log::INFO);
		Billrun_Factory::log((isset($data['data']) ? "import lines: " . count($data['data']) : "no data received"), Zend_Log::INFO);
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
