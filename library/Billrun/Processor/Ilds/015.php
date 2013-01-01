<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing processor for 015 class
 *
 * @package  Billing
 * @since    1.0
 */
class Billrun_Processor_015 extends Billrun_Processor {

	protected $type = '015';

	public function __construct($options) {

		parent::__construct($options);

		$this->data_structure = array(
			'record_type' => 1,
			'call_type' => 2,
			'caller_phone_no' => 10,
			'called_no' => 28,
			'call_start_dt' => 14,
			'chrgbl_call_dur' => 6,
			'rate_code' => 1,
			'call_charge_sign' => 1,
			'call_charge' => 11,
			'charge_code' => 2,
			'record_status' => 2,
		);

		$this->header_structure = array(
			'record_type' => 1,
			'file_type' => 15,
			'sending_company_id' => 10,
			'receiving_company_id' => 10,
			'sequence_no' => 6,
			'file_creation_date' => 14,
			'file_received_date' => 14,
			'file_status' => 2,
		);

		$this->trailer_structure = array(
			'record_type' => 1,
			'file_type' => 15,
			'sending_company_id' => 10,
			'receiving_company_id' => 10,
			'sequence_no' => 6,
			'file_creation_date' => 14,
			'total_phone_number' => 15, // WTF?!
			'total_charge_sign' => 1,
			'total_charge' => 15,
			'total_rec_no' => 6,
			'total_err_rec_no' => 6,
		);
	}

}