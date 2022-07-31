<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing exporter to CSV
 *
 * @package  Billing
 * @since    5.9
 */
class Billrun_Exporter_Csv extends Billrun_Exporter_File {
	
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
		$this->fixedWidth = Billrun_Util::getIn($this->config, 'exporter.format.type', 'delimiter') === 'fixed_width';
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
		return Billrun_Util::getIn($this->config, 'format.delimiter', ',');
	}
	
	/**
	 * see parent::formatValue
	 */
	protected function formatData($row, $type = 'data') {
		if (!$this->fixedWidth) {
			return $row;
		}

		switch ($type) {
			case 'header':
				$widthMappingField = 'header_mapping';
				break;
			case 'footer':
				$widthMappingField = 'footer_mapping';
				break;
			case 'data':
			default:
				$widthMappingField = 'fields_mapping';
				break;
		}
		foreach ($row as $field => $value) {
			$width = Billrun_Util::getIn($this->config, array('exporter', 'format', 'widths', $widthMappingField, $field), strlen($value));
			$row[$field] = str_pad($value, $width, ' ', STR_PAD_LEFT);
                        if (strlen($row[$field]) > $width){
                            $row[$field] = substr($row[$field], 0, $width);
                        }
		}
		return $row;
	}

	/**
	 * see parent::exportRowToFile
	 */
	protected function exportRowToFile($fp, $row, $type = 'data') {
		$rowToExport = $this->formatData($row, $type);
		fputs($fp, implode($rowToExport, $this->delimiter) . PHP_EOL);
	}

}

