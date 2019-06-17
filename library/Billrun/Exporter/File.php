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
 * @since    2.8
 */
abstract class Billrun_Exporter_File extends Billrun_Exporter_Bulk {
	
	static protected $type = 'file';
	
	const SEQUENCE_NUM_INIT = 1;
	
	protected $lastLogSequenceNum = null;
	protected $sequenceNum = null;
	
	/**
	 * get file name
	 */
	abstract protected function getFileName();
	
	/**
	 * export 1 line to a file
	 */
	protected abstract function exportRowToFile($fp, $row);
	
	/**
	 * get file path
	 */
	protected function getFilePath() {
		$defaultExportPath = Billrun_Factory::config()->getConfigValue(static::$type . '.export'. '');
		return $this->getConfig('file_path', $defaultExportPath);
	}
	
	/**
	 * gets file path for export
	 * 
	 * @return string
	 */
	protected function getExportFilePath() {
		return rtrim($this->getFilePath(), '/') . '/' . $this->getFileName();
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
		$dataToExport = $this->getDataToExport();
		foreach ($dataToExport as $row) {
			$this->exportRowToFile($fp, $row);
		}
		fclose($fp);
		$this->afterExport();
		return $dataToExport;
	}
	
	/**
	 * gets data to update log in DB
	 * 
	 * @return type
	 */
	protected function getLogData() {
		$fileName = $this->getFileName();
		$filePath = rtrim($this->getFilePath(), '/') . '/' . $fileName;
		
		return array(
			'exported_time' => date(self::base_dateformat),
			'file_name' => $fileName,
			'path' => $filePath,
			'sequence_num' => $this->getNextLogSequenceNumber(),
		);
	}
	
	protected function getNextLogSequenceNumberQuery() {
		return [
			'source' => 'export',
			'type' => static::$type,
		];
	}
		
	/**
	 * gets the next sequence number of the VPMN from log collection
	 */
	protected function getNextLogSequenceNumber() {
		if (is_null($this->lastLogSequenceNum)) {
			$query = $this->getNextLogSequenceNumberQuery();
			$sort = array(
				'export_time' => -1,
			);
			$lastSeq = $this->logCollection->query($query)->cursor()->sort($sort)->limit(1)->current()->get('sequence_num');
			if (is_null($lastSeq)) {
				$this->lastLogSequenceNum = self::SEQUENCE_NUM_INIT;
			} else {
				$this->lastLogSequenceNum = $lastSeq + 1;
			}
		}
		return $this->lastLogSequenceNum;
	}
	
	/**
	 * gets current sequence number for the file
	 * 
	 * @return string - number in the range of 00001-99999
	 */
	protected function getSequenceNumber() {
		if (is_null($this->sequenceNum)) {
			$seqNumLength = $this->getConfig('sequence_num_length', 5);
			$nextSequenceNum = $this->getNextLogSequenceNumber();
			$this->sequenceNum = sprintf('%0' . $seqNumLength . 'd', $nextSequenceNum % pow(10, $seqNumLength));
		}
		return $this->sequenceNum;
	}
	
}

