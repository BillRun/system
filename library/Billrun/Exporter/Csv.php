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
abstract class Billrun_Exporter_Csv extends Billrun_Exporter_File {
	
	static protected $type = 'csv';
	
	/**
	 * CSV delimiter
	 * 
	 * @var string
	 */
	protected $delimiter = '';
	
	public function __construct($options = array()) {
		parent::__construct($options);
		$this->delimiter = $this->getDelimiter();
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
		return $includeHeader ? [array_keys($this->getFieldsMapping())] : array();
	}
	
	/**
	 * see parent::exportRowToFile
	 */
	protected function exportRowToFile($fp, $row) {
		fputcsv($fp, $row, $this->delimiter);
	}
	
}

