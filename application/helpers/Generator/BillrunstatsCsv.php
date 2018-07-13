<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Flatten billrun generator class
 * require to generate csvs for comparison with older billing systems / charge using credit guard
 *
 * @todo this class should inherit from abstract class Generator_Golan
 * @package  Billing
 * @since    0.5
 */
class Generator_BillrunstatsCsv extends Generator_Billrunstats {

	protected $headers = null;
	protected $separator = ",";
	protected $filename = null;
	protected $file_path = null;

	public function __construct($options) {
		self::$type = 'billrunstatscsv';
		parent::__construct($options);
		$this->filename = "billrunstats" . $this->stamp . ".csv";
		$this->buildHeader();
		$this->file_path = $this->export_directory . DIRECTORY_SEPARATOR . $this->filename;
		$this->resetFile();
	}

	protected function buildHeader() {
		$this->headers = array(
			'aid' => 'aid',
			'billrun_key' => 'billrun_key',
			'sid' => 'sid',
			'subscriber_status' => 'subscriber_status',
			'current_plan' => 'current_plan',
			'next_plan' => 'next_plan',			
			'kosher' => 'kosher',
			'day' => 'day',
			'plan' => 'plan',
			'category' => 'category',
			'zone' => 'zone',
			'vat' => 'vat',
			'usagev' => 'usagev',
			'usaget' => 'usaget',
			'count' => 'count',
			'cost' => 'cost'
		);
	}

	protected function flushBuffer() {
		$this->writeRows();
		$this->resetBuffer();
	}

	protected function timeToFlush() {
		return (count($this->buffer) >= 50000);
	}

	protected function resetFile() {
		$this->writeToFile("", true);
	}

	protected function writeToFile($str, $overwrite = false) {
		if ($overwrite) {
			$ret = file_put_contents($this->file_path, $str);
		} else {
			$ret = file_put_contents($this->file_path, $str, FILE_APPEND);
		}
		return $ret;
	}

	protected function writeRows() {
		$file_contents = '';
		foreach ($this->buffer as $entity) {
			$row_contents = '';
			if (!is_array($entity)) {
				$entity = $entity->getRawData();
			}
			foreach ($this->headers as $key => $field_name) {
				$row_contents.=(isset($entity[$key]) ? $entity[$key] : "") . $this->separator;
			}

			$file_contents .= trim($row_contents, $this->separator) . PHP_EOL;
		}
		$this->writeToFile($file_contents);
	}

}
