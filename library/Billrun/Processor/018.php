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
class Billrun_Processor_018 extends Billrun_Processor_Base_Ilds {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = '018';

	public function __construct($options) {

		parent::__construct($options);

		$this->data_structure = array(
			'record_type' => 1,
			'call_type' => 2,
			'caller_phone_no' => 10,
			'called_no' => 18,
			'call_start_dt' => 14,
			'call_end_dt' => 14,
			'actual_call_dur' => 6,
			'chrgbl_call_dur' => 6,
			'call_charge_sign' => 1,
			'call_charge' => 11,
			'collection_ind' => 1,
			'record_status' => 2,
			'sequence_no' => 6,
			'correction_code' => 2,
			'filler' => 96,
		);


		$this->header_structure = array(
			'record_type' => 1,
			'file_type' => 3,
			'sending_company_id' => 4,
			'receiving_company_id' => 4,
			'sequence_no' => 6,
			'file_creation_date' => 14,
			'file_received_date' => 14,
			'file_status' => 2,
			'version_no' => 2,
			'filler' => 140,
		);

		$this->trailer_structure = array(
			'record_type' => 1,
			'file_type' => 3,
			'sending_company_id' => 4,
			'receiving_company_id' => 4,
			'sequence_no' => 6,
			'file_creation_date' => 14,
			'total_charge_sign' => 1,
			'total_charge' => 15,
			'total_rec_no' => 6,
			'total_err_rec_no' => 6,
			'filler' => 130,
		);
	}

}
