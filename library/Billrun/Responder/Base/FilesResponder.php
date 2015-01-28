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
		$types = array();
		if(self::$type == 'premium') {
			$types = Billrun_Factory::config()->getConfigValue('premium.providers');
		} else {
			$types[] = self::$type;
		}
		
		$retPaths = array();
		
		foreach ($types as $type) {
			
			Billrun_Factory::dispatcher()->trigger('beforeResponse', array('type' => $type , 'responder' => &$this));

			foreach ($this->getProcessedFilesForType($type) as $filename => $logLine) {
				$filePath = $this->workspace . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . $filename;
				if (!file_exists($filePath)) {
					Billrun_Factory::log()->log("NOTICE : SKIPPING $filename for type : " . $type . "!!! ,path -  $filePath not found!!", Zend_Log::NOTICE);
					continue;
				}

				$responseFilePath = $this->processFileForResponse($filePath, $logLine, $filename);
				if ($responseFilePath) {
					if(self::$type == 'premium') {
						$retPaths[] = $this->respondAFile($responseFilePath, $this->getResponseFilename($filename, $logLine), $logLine , $type);
					} else {
						$retPaths[] = $this->respondAFile($responseFilePath, $this->getResponseFilename($filename, $logLine), $logLine);
					}
				}
			}

			Billrun_Factory::dispatcher()->trigger('afterResponse', array('type' => $type , 'responder' => &$this));
		} 
		return $retPaths;
	}

	protected function getProcessedFilesForType($type) {
		$files = array();
		$log = Billrun_Factory::db()->logCollection();

		$logLines = $log->query(array('$or' => array(
						array('type' =>  $type),
						array('source' => $type)
					)))->exists('process_time')->notExists('response_time');
		foreach ($logLines as $logEntry) {
			$logEntry->collection($log);
			$files[$this->getFilenameFromLogLine($logEntry)] = $logEntry;
		}

		return $files;
	}

	abstract protected function processFileForResponse($filePath, $logLine);

	abstract protected function getResponseFilename($receivedFilename, $logLine);

	protected function respondAFile($responseFilePath, $fileName, $logLine) {
		Billrun_Factory::log()->log("Responding on : $fileName", Zend_Log::DEBUG);
		$data = $logLine->getRawData();
		$data['response_time'] = time();
		$logLine->setRawData($data);
		$logLine->save();
		return $responseFilePath;
	}

}

