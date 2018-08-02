<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing abstract exporter bulk (multiple rows at once) to CSV
 *
 * @package  Billing
 * @since    2.8
 */
abstract class Billrun_Exporter_Csv extends Billrun_Exporter_Bulk {
	
	static protected $type = 'csv';
	
	/**
	 * get CSV file name
	 */
	abstract protected function getFileName();
	
	/**
	 * get CSV file path
	 */
	protected function getFilePath() {
		$defaultExportPath = Billrun_Factory::config()->getConfigValue(static::$type . '.export'. '');
		return $this->getConfig('file_path', $defaultExportPath);
	}
	
	/**
	 * get delimiter character for 1 row in exported file
	 * 
	 * @return string
	 */
	protected function getDelimiter() {
		return $this->getConfig('delimiter', ',');
	}
	
	/**
	 * see parent::getHeader()
	 */
	protected function getHeader() {
		$includeHeader = $this->getConfig('include_header', true);
		return $includeHeader ? array_keys($this->getFieldsMapping()) : array();
	}

	/**
	 * exports data to CSV file
	 * 
	 * @return exported lines on success, false on failure
	 */
	function handleExport() {
		$filePath = rtrim($this->getFilePath(), '/') . '/' . $this->getFileName();
		$fp = fopen($filePath, 'w');
		if (!$fp) {
			Billrun_Log::getInstance()->log('CSV bulk export: Cannot open file', Zend_log::ERR);
			return false;
		}
		$dataToExport = $this->getDataToExport();
		$delimiter = $this->getDelimiter();
		foreach ($dataToExport as $row) {
			fputcsv($fp, $row, $delimiter);
		}
		fclose($fp);
		$this->afterExport();
		return $dataToExport;
	}
	
}

