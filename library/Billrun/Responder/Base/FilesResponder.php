<?php
/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing abstract receiver class
 *
 * @package  Billing
 * @since    1.0
 */
abstract class Billrun_Responder_Base_FilesResponder extends Billrun_Responder {

	/**
	 * general function to receive
	 *
	 * @return mixed
	 */
	public function respond() {
		
		$this->dispatcher->trigger('beforeResponse', array('type' => self::$type , 'responder' => &$this));
		
		$retPaths = array();
		
		foreach ($this->getProcessedFilesForType(self::$type) as $filename => $logLine) {
			$filePath = $this->workspace . DIRECTORY_SEPARATOR . self::$type . DIRECTORY_SEPARATOR . $filename;
			if (!file_exists($filePath)) {
				$this->log->log("NOTICE : SKIPPING $filename for type : " . self::$type . "!!! ,path -  $filePath not found!!", Zend_Log::NOTICE);
				continue;
			}

			$responseFilePath = $this->processFileForResponse($filePath, $logLine, $filename);
			if ($responseFilePath) {
				$retPaths[] = $this->respondAFile($responseFilePath, $this->getResponseFilename($filename, $logLine), $logLine);
			}
		}
		
		$this->dispatcher->trigger('afterResponse', array('type' => self::$type , 'responder' => &$this));
		
		return $retPaths;
	}

	protected function getProcessedFilesForType($type) {
		$files = array();
		if (!isset($this->db)) {
			$this->log->log("Billrun_Responder_Remote::getProcessedFilesForType - please providDB instance.", Zend_Log::DEBUG);
			return false;
		}

		$log = $this->db->getCollection(self::log_table);

		$logLines = $log->query()->equals('type', $type)->exists('process_time')->notExists('response_time');
		foreach ($logLines as $logEntry) {
			$logEntry->collection($log);
			$files[$logEntry->get('file')] = $logEntry;
		}

		return $files;
	}

	abstract protected function processFileForResponse($filePath, $logLine);

	abstract protected function getResponseFilename($receivedFilename, $logLine);

	protected function respondAFile($responseFilePath, $fileName, $logLine) {
		$data = $logLine->getRawData();
		$data['response_time'] = time();
		$logLine->setRawData($data);
		$logLine->save();
		return $responseFilePath;
	}

}

