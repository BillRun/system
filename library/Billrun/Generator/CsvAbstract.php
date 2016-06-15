<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Csv generator class
 *
 * @package  Billing
 * @since    0.5
 */
abstract class Billrun_Generator_CsvAbstract extends Billrun_Generator_Csv {

	/**
	 *
	 * @var string
	 */
	protected $pad_string = ' ';
	protected $pad_type = STR_PAD_RIGHT;
	protected $header_pad_length = array();

	/**
	 *
	 * @var array
	 */
	protected $pad_length = array();

	public function __construct($options) {
		parent::__construct($options);
		if (isset($options['pad_string'])) {
			$this->pad_string = $options['pad_string'];
		}
		if (isset($options['pad_type']) && strtoupper($options['pad_type']) == 'LEFT') {
			$this->pad_type = STR_PAD_LEFT;
		}
		if (isset($options['pad_length']) && is_array($options['pad_length'])) {
			$this->pad_length = Billrun_Util::verify_array($options['pad_length'], 'int');
		}
	}

	protected function getHeaderRowContent($entity,$pad_length = array()) {
		$row_contents = '';
		if (!empty($pad_length)){
			$this->pad_length = $pad_length;
		}
		$header_numeric_fields = Billrun_Factory::config()->getConfigValue('CGcsv.header.numeric_fields');
		for ($key = 0; $key < count($this->pad_length); $key++) {
			if (in_array($key,$header_numeric_fields)){ 
				$this->pad_type = STR_PAD_LEFT;
				$this->pad_string = '0';
			}
			else{
				$this->pad_type = STR_PAD_RIGHT;
				$this->pad_string = ' ';
			}
			$row_contents.=str_pad((isset($entity[$key]) ? substr($entity[$key], 0, $this->pad_length[$key]) : ''), $this->pad_length[$key], $this->pad_string, $this->pad_type);
		}
		return $row_contents;
	}
	
	
	protected function getRowContent($entity,$pad_length = array()) {
		$row_contents = '';
		if (!empty($pad_length)){
			$this->pad_length = $pad_length;
		}
		$data_numeric_fields = Billrun_Factory::config()->getConfigValue('CGcsv.data.numeric_fields');
		for ($key = 0; $key < count($this->pad_length); $key++) {
			if (in_array($key, $data_numeric_fields)){ 
				$this->pad_type = STR_PAD_LEFT;
				$this->pad_string = '0';
			}
			else{
				$this->pad_type = STR_PAD_RIGHT;
				$this->pad_string = ' ';
			}
			$row_contents.=str_pad((isset($entity[$key]) ? substr($entity[$key], 0, $this->pad_length[$key]) : ''), $this->pad_length[$key], $this->pad_string, $this->pad_type);
		}
			return $row_contents;
	}

	public function generate() {
		if (count($this->data)) {
			$this->writeHeaders();
			$this->writeRows();
		}
	}

}
