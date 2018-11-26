<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing abstract exporter bulk (multiple rows at once) to a file
 *
 * @package  Billing
 * @since    5.9
 */
abstract class Billrun_Exporter_File extends Billrun_Exporter {
	
	static protected $type = 'file';
	
	/**
	 * get file name
	 */
	protected function getFileName() {
		$fileName = $this->config['file_name'];
		$searchesAndReplaces = $this->getSearchesAndReplaces();
		return str_replace(array_keys($searchesAndReplaces), array_values($searchesAndReplaces), $fileName);
	}
	
	protected function getSearchesAndReplaces() {
		return array(
			'{$sequence_num}' => $this->getSequenceNumber(),
			'{$date_YYYYMMDDHHMMSS}' => $this->getTimeStamp(),
			'{$date}' => $this->getTimeStamp(),
		);
	}
	
	/**
	 * export 1 line to a file
	 */
	protected abstract function exportRowToFile($fp, $row, $type = 'data');
	
	/**
	 * get file path
	 */
	protected function getFilePath() {
		$sharedPath = Billrun_Util::getBillRunSharedFolderPath(Billrun_Util::getIn($this->config, 'workspace', 'workspace'));
		return rtrim($sharedPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'export' . DIRECTORY_SEPARATOR . date("Ym") . DIRECTORY_SEPARATOR . substr(md5(serialize($this->config)), 0, 7) . DIRECTORY_SEPARATOR;
	}
	
	/**
	 * gets file path for export
	 * 
	 * @return string
	 */
	protected function getExportFilePath() {
		$filePath = $this->getFilePath();
		if (!file_exists($filePath)) {
			mkdir($filePath, 0777, true);
		}
		return rtrim($filePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->getFileName();
	}

	/**
	 * exports data to a file
	 * 
	 * @return exported lines on success, false on failure
	 */
	function handleExport() {
		$filePath = $this->getExportFilePath();
		$fp = fopen($filePath, 'w');
		if (!$fp) {
			Billrun_Log::getInstance()->log('File bulk export: Cannot open file "' . $filePath . '"', Zend_log::ERR);
			return false;
		}
		$exportedData = array();
		if (!empty($this->headerToExport)) {
			$this->exportRowToFile($fp, $this->headerToExport, 'header');
			$exportedData[] = $this->headerToExport;
		}

		foreach ($this->rowsToExport as $row) {
			$this->exportRowToFile($fp, $row);
			$exportedData[] = $row;
		}
		if (!empty($this->footerToExport)) {
			$this->exportRowToFile($fp, $this->footerToExport, 'footer');
			$exportedData[] = $this->footerToExport;
		}
		fclose($fp);
		return $exportedData;
	}
	
	/**
	 * see parent::afterExport
	 */
	public function afterExport() {
		$this->sendFile();
		parent::afterExport();
	}
	
	/**
	 * sends the exported file to the location/server configured
	 */
	protected function sendFile() {
		foreach (Billrun_Util::getIn($this->config, 'senders', array()) as $senderConfig) {
			$sender = Billrun_Sender::getInstance($senderConfig);
			if (!$sender) {
				Billrun_Factory::log()->log("Cannot get sender. details: " . print_R($senderConfig, 1), Zend_Log::ERR);
				continue;
			}
			$sender->send($this->getExportFilePath());
		}
	}


	/**
	 * gets data to update log in DB
	 * 
	 * @return type
	 */
	protected function getLogData() {
		$logData = parent::getLogData();
		$fileName = $this->getFileName();
		$filePath = rtrim($this->getFilePath(), '/') . '/' . $fileName;
		$logData['file_name'] = $fileName;
		$logData['path'] = $filePath;
		
		return $logData;
	}
	
}

