<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
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
		$header['type'] = static::$type;
		$header['file'] = basename($this->filePath);
		$header['stamp'] = md5(serialize($header));
		$header['process_time'] = date(self::base_dateformat);

		return $header;
	}

	/**
	 * This function should be used to build a Data row
	 * @param $data the raw row data
	 * @return Array that conatins all the parsed and processed data.
	 */
	public function buildDataRow($data) {
		$row = false;
		$this->getParser()->setLine($data);
		$rawRow = $this->getParser()->parse();
		if ($rawRow) {
			$row = $this->filterFields($rawRow);
			$row['type'] = static::$type;
			$row['source'] = self::$type;
			$row['header_stamp'] = $this->data['header']['stamp'];
			$row['file'] = basename($this->filePath);
			$row['stamp'] = md5(serialize($row));
			$row['process_time'] = date(self::base_dateformat);
		}
		return $row;
	}

	/**
	 * Create an trailer record.
	 * @param $data  the trailer record data.
	 * @return Array an array to be used as the trailer data record.
	 */
	public function buildTrailer($data) {
		$trailer = array();
		$trailer['data'] = ($data && !is_array($data)) ? $this->getParser()->parseTrailer($data) : $data;
		$trailer['type'] = static::$type;
		$trailer['header_stamp'] = $this->data['header']['stamp'];
		$trailer['file'] = basename($this->filePath);
		$trailer['stamp'] = md5(serialize($trailer));
		$trailer['process_time'] = date(self::base_dateformat);
		
		return $trailer;
	}

	/**
	 * filter the record row data fields from the records
	 * (The required field can be written in the config using <type>.fields_filter)
	 * @param Array		$rawRow the full data record row.
	 * @return Array	the record row with filtered only the requierd fields in it  
	 *					or if no filter is defined in the configuration the full data record.
	 */
	protected function filterFields($rawRow) {
		$row = array();
		
		$requiredFields = Billrun_Factory::config()->getConfigValue( static::$type.'.fields_filter',false,'array');
		if($requiredFields) {
			foreach($requiredFields as $field) {
				if(isset($rawRow[$field])) {
					$row[$field] = $rawRow[$field];
				}
			}
		} else {
			return $rawRow;
		}
		
		return $row;
	}
	
}
