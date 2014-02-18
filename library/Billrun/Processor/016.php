<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing processor for 016 class
 *
 * @package  Billing
 * @since    1.0
 */
class Billrun_Processor_016 extends Billrun_Processor_Base_Ilds {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = '016';

	/**
	 * (Override) Get the type of the currently parsed line.
	 * @param $line  string containing the parsed line.
	 * @return Character representing the line type
	 * 		'H' => Header
	 * 		'D' => Data
	 * 		'T' => Tail
	 */
	protected function getLineType($line) {
		$this->data['header'] = 'fake';  // Add fake beacause 016 has no header, we should not treat it in generate process
		$this->data['trailer'] = 'fake'; // Add fake beacause 016 has no trailer, we should not treat it in generate process
		return 'D';
	}

	public function __construct($options) {

		parent::__construct($options);

		$this->data_structure = array(
			'records_type' => 3,
			'calling_number' => 15,
			'call_start_time' => 13,
			'call_end_time' => 13,
			'called_number' => 18,
			'is_in_glti' => 1,
			'prepaid' => 1,
			'duration' => 10,
			'sampleDurationInSec' => 8,
			'charge' => 10,
			'origin_carrier' => 10,
			'origin_file_name' => 100,
		);

		$this->header_structure = array();

		$this->trailer_structure = array();
	}

}
