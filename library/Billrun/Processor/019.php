<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing processor for 018 class
 *
 * @package  Billing
 * @since    0.5
 */
class Billrun_Processor_019 extends Billrun_Processor_Base_Ilds {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = '019';

	public function __construct($options) {

		parent::__construct($options);

		$this->data_structure = array(
			'record_type' => 1,
			'call_type' => 2,
			'caller_phone_no' => 10,
			'called_no' => 18,
			'call_start_dt' => 14,
			'call_end_dt' => 14,
			'chrgbl_call_dur' => 6,
			'call_charge_sign' => 1,
			'call_charge' => 11,
			'collection_ind' => 2,
			'record_status' => 2,
//			'sequence_no' => 6,
//			'correction_code' => 2,
//			'filler' => 96,
		);


		$this->header_structure = array(
			'record_type' => 1,
			'file_type' => 15,
			'sending_company_id' => 10,
			'receiving_company_id' => 10,
			'sequence_no' => 6,
			'file_creation_date' => 12,
			'file_sending_date' => 12,
			'file_status' => 2,
			'filler' => 187,
		);

		$this->trailer_structure = array(
			'record_type' => 1,
			'file_type' => 15,
			'sending_company_id' => 10,
			'receiving_company_id' => 10,
			'sequence_no' => 6,
			'file_creation_date' => 12,
			'sum_of_number' => 15, 
			'total_charge_sign2' => 1,
			'total_charge2' => 15,
			'total_charge_sign' => 1,
			'total_charge' => 15,
			'total_err_rec_no' => 6,
		);
	}

}
