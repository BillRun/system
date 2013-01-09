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
	 * the responder export base path.
	 * @var string directory path
	 */
	protected $exportDir;

	public function __construct($options) {

		parent::__construct($options);

		if (isset($options['export-path'])) {
			$this->exportDir = $options['export-path'];
		} else {
			$this->exportDir = $this->config->response->export->path;
		}
	}

	/**
	 * general function to receive
	 *
	 * @return mixed
	 */
	public function respond() {

		foreach($this->getProcessedFilesForType($this->type) as $filename => $logLine) {
			$filePath = $this->workPath . DIRECTORY_SEPARATOR . $this->type . DIRECTORY_SEPARATOR . $filename ;
			if (!file_exists($filePath)) {
				print("NOTICE : SKIPPING $filename for type : $this->type !!! ,path -  $filePath not found!!\n");
				continue;
			}

			$responseFilePath = $this->processFileForResponse($filePath, $logLine,$filename);
			if($responseFilePath) {
				$this->respondAFile($responseFilePath, $this->getResponseFilename($filename,$logLine), $logLine);
			}
		}
	}

	protected function getProcessedFilesForType($type) {
		$files = array();
		if (!isset($this->db)) {
			$this->log->log("Billrun_Responder_Remote::getProcessedFilesForType - please providDB instance.",Zend_Log::DEBUG);
			return false;
		}

		$log = $this->db->getCollection(self::log_table);

		$logLines = $log->query()->equals('type',$type)->exists('process_time')->notExists('response_time');
		foreach($logLines as $logEntry) {
			$logEntry->collection($log);
			$files[$logEntry->get('file')] = $logEntry;
		}

		return $files;
	}


	abstract protected function processFileForResponse($filePath,$logLine) ;

	abstract protected function getResponseFilename($receivedFilename,$logLine) ;

	protected function respondAFile($responseFilePath, $fileName, $logLine) {
		$data = $logLine->getRawData();
		$data['response_time'] = time();
		$logLine->setRawData($data);
		$logLine->save();
	}


}


