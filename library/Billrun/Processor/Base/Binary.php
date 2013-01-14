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
 * @since    1.0
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
	protected function buildHeader($data) {
		$header = array();
		$header['data'] = utf8_encode($data);
		$header['type'] = self::$type;
		$header['file'] = basename($this->filePath);
		$header['process_time'] = date('Y-m-d h:i:s');
		$header['stamp'] = md5(serialize($header));
		return $header;
	}

	/**
	 * This function should be used to build a Data row
	 * @param $data the raw row data
	 * @return Array that conatins all the parsed and processed data.
	 */
	protected function buildDataRow($data) {
		$this->parser->setLine($data);
		$row = $this->parser->parse();
		if ($row) {
			$row['type'] = self::$type;
			$row['header_stamp'] = $this->data['header']['stamp'];
			$row['file'] = basename($this->filePath);
			$row['process_time'] = date('Y-m-d h:i:s');
		}
		return $row;
	}

	/**
	 * Create an trailer record.
	 * @param $data  the trailer record data.
	 * @return Array an array to be used as the trailer data record.
	 */
	protected function buildTrailer($data) {
		$trailer = array();
		$trailer['data'] = utf8_encode($data);
		$trailer['type'] = self::$type;
		$trailer['header_stamp'] = $this->data['header']['stamp'];
		$trailer['file'] = basename($this->filePath);
		$trailer['process_time'] = date('Y-m-d h:i:s');
		return $trailer;
	}

}
