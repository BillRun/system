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
		);
	}
	
}

