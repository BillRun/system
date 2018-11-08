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
	
	protected $fixedWidth = false;
	
	public function __construct($options = array()) {
		parent::__construct($options);
		$this->fixedWidth = $this->getConfig('fixed_width', false);
		$this->delimiter = $this->getDelimiter();
	}
	
	/**
	 * get delimiter character for 1 row in exported file
	 * 
	 * @return string
	 */
	protected function getDelimiter() {
		if ($this->fixedWidth) {
			return '';
		}
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
	 * see parent::formatValue
	 */
	protected function formatValue($value, $field, $fieldMapping) {
		if (!$this->fixedWidth) {
			return parent::formatValue($value, $field, $fieldMapping);
		}
		
		$padding = Billrun_Util::getIn($fieldMapping, 'padding', ' ');
		$width = Billrun_Util::getIn($fieldMapping, 'width', strlen($value));
		$padDirection = Billrun_Util::getIn($fieldMapping, 'pad_direction', 'left') == 'right' ? STR_PAD_RIGHT : STR_PAD_LEFT;
		return str_pad($value, $width, $padding, $padDirection);
	}

	/**
	 * see parent::exportRowToFile
	 */
	protected function exportRowToFile($fp, $row) {
		fputs($fp, implode($row, $this->delimiter)."\n");
	}
	
}

