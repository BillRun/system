<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing processor for 017 class
 *
 * @package  Billing
 * @since    1.0
 */
class Billrun_Processor_017 extends Billrun_Processor_Base_Ilds {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = '017';

	public function __construct($options) {

		parent::__construct($options);

		$this->data_structure = array(
			'record_type' => 1,
			'call_type' => 2,
			'caller_phone_no' => 9,
			'called_no' => 14,
			'orig_country_desc' => 15,
			'call_start_dt' => 14,
			'chrgbl_call_dur' => 7,
			'call_charge_sign' => 1,
			'call_charge' => 15,
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
			'sum_of_number_sign' => 1,
			'sum_of_charges' => 15,
			'sum_of_charges_sign' => 1,
			'sum_of_charge' => 15,
			'num_of_records' => 6,
			'sum_of_err_rec' => 6,
			'last_field' => 40,
		);
	}

}
