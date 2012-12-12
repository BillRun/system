<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing processor for 012 class
 *
 * @package  Billing
 * @since    1.0
 */
class processor_012 extends processor
{

	protected $type = '012';

	public function __construct($options)
	{
		parent::__construct($options);

		$this->data_structure = array(
			'record_type' => 1,
			'call_type' => 2,
			'caller_phone_no' => 9,
			'call_start_dt' => 14,
			'chrgbl_call_dur' => 6,
			'called_no' => 24,
			'country_desc' => 20,
			'collection_ind' => 1,
			'record_id' => 22,
			'charge_code' => 1,
			'call_charge_sign' => 1,
			'call_charge' => 15,
			'record_status' => 2,
		);


		$this->header_structure = array(
			'record_type' => 1,
			'file_type' => 10,
			'sending_company_id' => 10,
			'receiving_company_id' => 10,
			'sequence_no' => 6,
			'file_creation_date' => 12,
			'file_received_date' => 12,
			'file_status' => 2,
		);

		$this->trailer_structure = array(
			'record_type' => 1,
			'file_type' => 10,
			'sending_company_id' => 10,
			'receiving_company_id' => 10,
			'sequence_no' => 6,
			'file_creation_date' => 12,
			'total_phone_number' => 15, // WTF?!
			'total_charge_sign' => 1,
			'total_charge' => 15,
			'total_rec_no' => 6,
			'total_err_rec_no' => 6,
		);
	}


}