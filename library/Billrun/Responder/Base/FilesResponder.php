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

	use Billrun_Traits_FileOperations;
	
	public function __construct($options) {
		parent::__construct($options);
	}
	/**
	 * general function to receive
	 *
	 * @return mixed
	 */
	public function respond() {
		$retPaths = array();
		
		foreach ($this->getProcessedFilesForType(self::$type) as $filename => $logLine) {
			$filePath = $this->getFilePath($filename, $logLine);
			if (!file_exists($filePath)) {
				$this->log->log("NOTICE : SKIPPING $filename for type : " . self::$type . "!!! ,path -  $filePath not found!!", Zend_Log::NOTICE);
				continue;
			}

			$responseFilePath = $this->processFileForResponse($filePath, $logLine, $filename);
			if ($responseFilePath) {
				$retPaths[] = $this->respondAFile($responseFilePath, $this->getResponseFilename($filename, $logLine), $logLine);
			}
		}
		
		return $retPaths;
	}
	
	/**
	 * get a list  of all the files that were processed by the system for a given file type.
	 * @param string $type the type to find processed files for.
	 * @return array an contain the processed files entries for the DB.
	 */
	protected function getProcessedFilesForType($type) {
		$files = array();
		if (!isset($this->db)) {
			$this->log->log("Billrun_Responder_Remote::getProcessedFilesForType - please providDB instance.", Zend_Log::DEBUG);
			return false;
		}

		$log = $this->db->getCollection(self::log_table);

		$logLines = $log->query()->equals('source', $type)->exists('process_time')->notExists('response_time');
		foreach ($logLines as $logEntry) {
			$logEntry->collection($log);
			$filename =$this->getFilenameFromLogLine($logEntry);// TODO (27/06/2013) remove  backward compatiblity REMOVE
			$files[$filename] = $logEntry;
		}

		return $files;
	}

	/**
	 * Process a file for response.
	 * @param string $filePath the path to the file to response.
	 * @param Mongoloid_Entity $logLine the  file log line in the DB.
	 * @return the process respose  file path.
	 */
	abstract protected function processFileForResponse($filePath, $logLine);

	/**
	 * Get the file name for the response file.
	 * @param string  the  received filename.
	 * @param Mongoloid_Entity $logLine the  file log line in the DB.
	 * @return string the response  file name.
	 */
	abstract protected function getResponseFilename($receivedFilename, $logLine);
	
	/**
	 * Marked  file as responded to.
	 * @param string $responseFilePath the response file path
	 * @param string $fileName the filename that was responseded
	 * @param \Mongodloid_Entity $logLine the log line (from the DB) that represent the file
	 * @return string the response file path.
	 */
	protected function respondAFile($responseFilePath, $fileName, $logLine) {
		$data = $logLine->getRawData();
		$data['response_time'] = time();
		$logLine->setRawData($data);
		$logLine->save();
		return $responseFilePath;
	}
	
	/**
	 * Get the path to the file to respone to.
	 * @param type $filename
	 * @param type $logLine
	 * @return string
	 */
	protected function getFilePath($filename,$logLine) {
		$filePath =  $this->workspace . DIRECTORY_SEPARATOR . self::$type . DIRECTORY_SEPARATOR . $filename;
		if (!file_exists($filePath)) {
			for ($i = 0; $i < count($this->backupPaths); $i++) {			
				$filePath = $this->getFileBackupPath($this->backupPaths[$i], $filename, $logLine)  . DIRECTORY_SEPARATOR . $filename;
				if(file_exists($filePath)) {
					break;
				}
			}
		}
		return $filePath;
	}

}

