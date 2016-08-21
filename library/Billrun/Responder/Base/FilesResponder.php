<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing abstract receiver class
 *
 * @package  Billing
 * @since    0.5
 */
abstract class Billrun_Responder_Base_FilesResponder extends Billrun_Responder {

	/**
	 * general function to receive
	 *
	 * @return mixed
	 */
	public function respond() {

		Billrun_Factory::dispatcher()->trigger('beforeResponse', array('type' => self::$type, 'responder' => &$this));

		$retPaths = array();

		foreach ($this->getProcessedFilesForType(self::$type) as $filename => $logLine) {
			$filePath = $this->workspace . DIRECTORY_SEPARATOR . self::$type . DIRECTORY_SEPARATOR . $filename;
			if (!file_exists($filePath)) {
				Billrun_Factory::log("Skipping $filename for type : " . self::$type . ". Path $filePath not found!", Zend_Log::ERR);
				continue;
			}

			$responseFilePath = $this->processFileForResponse($filePath, $logLine, $filename);
			if ($responseFilePath) {
				$retPaths[] = $this->respondAFile($responseFilePath, $this->getResponseFilename($filename, $logLine), $logLine);
			}
		}

		Billrun_Factory::dispatcher()->trigger('afterResponse', array('type' => self::$type, 'responder' => &$this));

		return $retPaths;
	}

	protected function getProcessedFilesForType($type) {
		$files = array();
		$log = Billrun_Factory::db()->logCollection();

		$logLines = $log->query()->equals('type', $type)->exists('process_time')->notExists('response_time');
		foreach ($logLines as $logEntry) {
			$files[$logEntry->get('file')] = $logEntry;
		}

		return $files;
	}

	abstract protected function processFileForResponse($filePath, $logLine);

	abstract protected function getResponseFilename($receivedFilename, $logLine);

	protected function respondAFile($responseFilePath, $fileName, $logLine) {
		Billrun_Factory::log("Responding on : $fileName", Zend_Log::DEBUG);
		$data = $logLine->getRawData();
		$data['response_time'] = time();
		$logLine->setRawData($data);
		Billrun_Factory::db()->logCollection()->save($logLine);
		return $responseFilePath;
	}

}
