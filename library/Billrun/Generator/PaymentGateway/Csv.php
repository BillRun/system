<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Generator CSV for payment gateways files
 */
class Billrun_Generator_PaymentGateway_Csv {
	
	protected $data = array();
	protected $headers = array();
	protected $delimeter = '';
	protected $fixedWidth = false;
	protected $padDirDef = STR_PAD_LEFT;
	protected $padCharDef = '';
	protected $fileName; 

	public function __construct($options) {
		$this->fixedWidth = isset($options['type']) && ($options['type'] == 'fixed') ? true : false;
		$this->data = isset($options['data']) ? $options['data'] : $this->data;
		$this->headers = isset($options['headers']) ? $options['headers'] : $this->headers;
		$this->delimeter = isset($options['delimeter']) ? $options['delimeter'] : $this->delimeter;
	//	if (!$this->validateOptions($options)) {
	//		Billrun_Factory::log("Missing options when generating payment gateways csv file for file type " . $options['file_type'], Zend_Log::DEBUG);
	//		return false;
	//	}
		$this->fileName = $options['file_name'];
	}
	
	protected function validateOptions($options) {
		if (isset($options['type']) && !in_array($options['type'], array('fixed', 'separator'))) {
			return false;
		}
		if (!isset($options['file_name'])) {
			return false;
		}
		if ($this->fixedWidth) {
			foreach ($this->data as $dataObj) {
				if (!isset($dataObj['padding']['length'])) {
					Billrun_Factory::log("Missing padding length definitions for " . $options['file_type'], Zend_Log::DEBUG);
					return false;
				}
			}
		}		
		return true;
	}
	
	public function generate() {
		if (count($this->data)) {
			$this->writeHeaders();
			$this->writeRows();
		}
		return;
	}
	
	protected function writeToFile($str) {
		return file_put_contents($this->filePath, $str);
	}
	
	
	
	
	
	
	
	
	
	
	
	
	
	protected function writeHeaders() {
		$fileContents = '';
		$counter = 0;
		foreach ($this->headers as $entity) {
			$counter++;
			if (!is_array($entity)) {
				$entity = $entity->getRawData();
			}
			$fileContents .= $this->getHeaderRowContent($entity);
			$fileContents .= PHP_EOL;
			if ($counter == 50000) {
				$this->writeToFile($fileContents);
				$fileContents = '';
				$counter = 0;
			}
		}
		$this->writeToFile($fileContents);
	}
		
	protected function writeRows() {
		$fileContents = '';
		$counter = 0;
		foreach ($this->data as $index => $entity) {
			$counter++;
			if (!is_array($entity)) {
				$entity = $entity->getRawData();
			}
			$padLength = $this->generateStructure['pad_length_data'];
			$fileContents .= $this->getRowContent($entity, $padLength);
			if ($index < count($this->customers)-1){
				$fileContents.= PHP_EOL;
			}
			if ($counter == 50000) {
				$this->writeToFile($fileContents);
				$fileContents = '';
				$counter = 0;
			}
		}
		$this->writeToFile($fileContents);
	}
	
	protected function getHeaderRowContent($entity) {
		$row_contents = '';
		for ($key = 0; $key < count($this->pad_length); $key++) {
			$row_contents.=str_pad((isset($entity[$key]) ? substr($entity[$key], 0, $this->pad_length[$key]) : ''), $this->pad_length[$key], $this->pad_string, $this->pad_type);
		}
		return $row_contents;
	}
	
	protected function getRowContent($entity,$pad_length = array()) {
		$this->pad_type = STR_PAD_LEFT;
		$row_contents = '';
		if (!empty($pad_length)){
			$this->pad_length = $pad_length;
		}
		$data_numeric_fields = $this->generateStructure['data']['numeric_fields'];
		for ($key = 0; $key < count($this->pad_length); $key++) {
			if (in_array($key, $data_numeric_fields)){
				$this->pad_string = '0';
			}
			else{
				$this->pad_string = ' ';
			}
			$row_contents.=str_pad((isset($entity[$key]) ? substr($entity[$key], 0, $this->pad_length[$key]) : ''), $this->pad_length[$key], $this->pad_string, $this->pad_type);
		}
		return $row_contents;
	}
	
	
	
	
	
	
	
	
	
	
	
	


//	/**
//	 * see parent::formatValue
//	 */
//	protected function formatData($row, $type = 'data') {
//		if (!$this->fixedWidth) {
//			return $row;
//		}
//
//		switch ($type) {
//			case 'header':
//				$widthMappingField = 'header_mapping';
//				break;
//			case 'footer':
//				$widthMappingField = 'footer_mapping';
//				break;
//			case 'data':
//			default:
//				$widthMappingField = 'fields_mapping';
//				break;
//		}
//		foreach ($row as $field => $value) {
//			$width = Billrun_Util::getIn($this->config, array('exporter', 'format', 'widths', $widthMappingField, $field), strlen($value));
//			$row[$field] = str_pad($value, $width, ' ', STR_PAD_LEFT);
//		}
//		return $row;
//	}
//
//	/**
//	 * see parent::exportRowToFile
//	 */
//	protected function exportRowToFile($fp, $row, $type = 'data') {
//		$rowToExport = $this->formatData($row, $type);
//		fputs($fp, implode($rowToExport, $this->delimiter) . PHP_EOL);
//	}

//}


	
	
	
}

