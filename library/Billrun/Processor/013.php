<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing processor for 013 class
 *
 * @package  Billing
 * @since    1.0
 */
class Billrun_Processor_013 extends Billrun_Processor {

	protected $type = '013';

	/**
	 * (Override) Get the type of the currently parsed line.
	 * @param $line  string containing the parsed line.
	 * @return Character representing the line type
	 * 		'H' => Header
	 * 		'D' => Data
	 * 		'T' => Tail
	 */
	protected function getLineType($line) {
		if (substr($line, 0, 1) == 'D') {
			return 'D';
		}

		$type = substr($line, 11, 1);
		if ($type == 'F') {
			return 'T';
		}
		return $type;
	}

	public function __construct($options) {
		
		parent::__construct($options);

		$this->data_structure = array(
			'record_type' => 1,
			'operator_code' => 3,
			'call_type' => 1,
			'caller_phone_no' => 10,
			'call_start_dt' => 14,
			'call_end_dt' => 14,
			'called_no' => 16,
			'chrgbl_call_dur' => 6,
			'call_charge' => 11,
			'correction_code' => 2,
		);


		$this->header_structure = array(
			'filename' => 11,
			'record_type' => 1,
			'file_creation_date' => 8,
			'file_received_time' => 6,
		);

		$this->trailer_structure = array(
			'filename' => 11,
			'record_type' => 1,
			'file_creation_date' => 8,
			'file_received_time' => 6,
			'file_size' => 9,
			'line_count' => 9,
			'total_charge' => 17,
		);
	}

}