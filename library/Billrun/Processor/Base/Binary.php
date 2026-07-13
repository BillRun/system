<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing  processor binary class
 *
 * @package  Billing
 * @since    0.5
 */
abstract class Billrun_Processor_Base_Binary extends Billrun_Processor {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'binary';

	/**
	 * create an header record
	 * @param $data  the header record data.
	 * @return Array an array to be used as the header data record.
	 */
	public function buildHeader($data) {
		$header = array();
		$header['data'] = $data ? $this->getParser()->parseHeader($data) : $data;
		$header['stamp'] = md5(serialize($header));
		$header['type'] = static::$type;
		$header['file'] = basename($this->filePath);

		$header['process_time'] = new Mongodloid_Date();

		return $header;
	}

	/**
	 * This function should be used to build a Data row
	 * @param $data the raw row data
	 * @return Array that conatins all the parsed and processed data.
	 */
	public function parseData($data, $fileHandle) {
		$row = false;
		$this->getParser()->setLine($data);
		$rawRow = $this->getParser()->parse($fileHandle);
		$newRowStruct = [];
		if ($rawRow) {
			$row = $this->filterFields($rawRow);
			$newRowStruct['uf'] = $row;
			$newRowStruct['stamp'] = md5(serialize($row));
			$newRowStruct['type'] = static::$type;
			$newRowStruct['source'] = self::$type;
			$newRowStruct['file'] = basename($this->filePath);
			$newRowStruct['log_stamp'] = $this->getFileStamp();
			$newRowStruct['process_time'] = new Mongodloid_Date();
		}
		return $newRowStruct;
	}

	/**
	 * Create an trailer record.
	 * @param $data  the trailer record data.
	 * @return Array an array to be used as the trailer data record.
	 */
	public function buildTrailer($data) {
		$trailer = array();
		$trailer['data'] = ($data && !is_array($data)) ? $this->getParser()->parseTrailer($data) : $data;
		$trailer['stamp'] = md5(serialize($trailer));
		$trailer['type'] = static::$type;
		$trailer['header_stamp'] = isset($this->data['header']['stamp']) ? $this->data['header']['stamp'] : 'no_header_stamp';
		$trailer['file'] = basename($this->filePath);
		$trailer['process_time'] = new Mongodloid_Date();

		return $trailer;
	}

	/**
	 * filter the record row data fields from the records
	 * (The required field can be written in the config using <type>.fields_filter)
	 * @param Array		$rawRow the full data record row.
	 * @return Array	the record row with filtered only the requierd fields in it  
	 * 					or if no filter is defined in the configuration the full data record.
	 */
	protected function filterFields($rawRow) {
		$row = array();

		$requiredFields = Billrun_Factory::config()->getConfigValue(static::$type . '.fields_filter', array(), 'array');
		if (!empty($requiredFields)) {
			foreach ($requiredFields as $field) {
				if (isset($rawRow[$field])) {
					$row[$field] = $rawRow[$field];
}
			}
		} else {
			return $rawRow;
		}

		return $row;
	}

}
