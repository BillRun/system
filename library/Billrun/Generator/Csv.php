<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Csv generator class
 *
 * @package  Billing
 * @since    0.5
 */
abstract class Billrun_Generator_Csv extends Billrun_Generator {

	protected $data = null;
	protected $headers = null;
	protected $separator = ",";
	protected $row_separator = "line_break";	
	protected $filename = null;
	protected $file_path = null;
	
	/**
	 *
	 * @var string
	 */
	protected $pad_string = ' ';
	protected $pad_type = STR_PAD_RIGHT;

	/**
	 *
	 * @var array
	 */
	protected $pad_length = array();
	protected $header_pad_length = array();
	
	

	public function __construct($options) {
		parent::__construct($options);
		$this->setFilename();
		$this->buildHeader();
		$this->file_path = $this->export_directory . DIRECTORY_SEPARATOR . $this->filename;
		$this->resetFile();
		if (isset($options['pad_string'])) {
			$this->pad_string = $options['pad_string'];
		}
		if (isset($options['pad_type']) && strtoupper($options['pad_type']) == 'LEFT') {
			$this->pad_type = STR_PAD_LEFT;
		}
		if (isset($options['pad_length']) && is_array($options['pad_length'])) {
			$this->pad_length = Billrun_Util::verify_array($options['pad_length'], 'int');
		}
		$row_separator = Billrun_Util::getIn($options, 'row_separator', 'line_break');
		$this->row_separator = $row_separator == 'line_break' ? PHP_EOL : $row_separator;
	}

	/**
	 * write row to csv file for generating info into in
	 * 
	 * @param string $path the path to append into
	 * @param string $str the content to write
	 * 
	 * @return boolean true if succes to write info else false
	 */
	protected function writeToFile($str, $overwrite = false) {
		if ($overwrite) {
			$ret = file_put_contents($this->file_path, $str);
		} else {
			$ret = file_put_contents($this->file_path, $str, FILE_APPEND);
		}
		return $ret;
	}

	abstract protected function buildHeader();

	/**
	 * execute the generate action
	 */
	public function generate() {
		if (count($this->data)) {
			$this->writeHeaders();
			$this->writeRows();
		}
	}

	protected function writeRows() {
		$file_contents = '';
		$counter = 0;
		foreach ($this->data as $entity) {
			$counter++;
			$row_contents = '';
			if (!is_array($entity)) {
				$entity = $entity->getRawData();
			}
			foreach ($this->headers as $key => $field_name) {
				$row_contents.=(isset($entity[$key]) ? $entity[$key] : "") . $this->separator;
			}

			$file_contents .= trim($row_contents, $this->separator) . $this->row_separator;
			if ($counter == 50000) {
				$this->writeToFile($file_contents);
				$file_contents = '';
				$counter = 0;
			}
		}
		$this->writeToFile($file_contents);
	}

	protected function writeHeaders() {
		$header_str = implode($this->headers, $this->separator) . $this->row_separator;
		$this->writeToFile($header_str);
	}

	abstract protected function setFilename();

	protected function resetFile() {
		$this->writeToFile("", true);
	}

}
