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
 * @since    1.0
 */
class Billrun_Processor_014 extends Billrun_Processor_Base_Ilds {

	protected $type = '014';
	const ITS_ON_GOLAN_DATE = "07/02/2012";

	public function __construct($options) {

		parent::__construct($options);

		$this->data_structure = array(
			'record_type' => 1,
			'caller_msi' => 3,
			'caller_phone_no' => 11,
			'called_no' => 28,
			'call_start_dt' => 14,
			//'call_start_tm' => 6,
			'actual_call_dur' => 6,
			'chrgbl_call_dur' => 6,
			'units' => 4,
			'call_charge_sign' => 1,
			'call_charge' => 11,
			'collection_ind' => 1,
			'collection_ind2' => 1,
			'provider_subscriber_type' => 1,
			'record_status' => 2,
// 			'sequence_no' => 6,
// 			'correction_code' => 2,
// 			'call_type' => 2,
			'filler' => 87,
		);


		$this->header_structure = array(
			'record_type' => 1,
			'file_type' => 14,
			'sending_company_id' => 3,
			'receiving_company_id' => 3,
			'sequence_no' => 5,
			'file_creation_date' => 8,
			'file_creation_time' => 6,
			'file_received_date' => 8,
			'file_received_time' => 6,
			'file_status' => 2,
		);

		$this->trailer_structure = array(
			'record_type' => 1,
			'file_type' => 14,
			'sending_company_id' => 3,
			'receiving_company_id' => 3,
			'sequence_no' => 5,
			'file_creation_date' => 8,
			'file_creation_time' => 6,
			'file_received_date' => 8,
			'file_received_time' => 6,
			'total_charge_sign' => 1,
			'total_charge' => 16,
			'total_rec_no' => 6,
			'total_valid_rec_no' => 6,
			'total_err_rec_no' => 6,
		);
	}

	/**
	 * Check is a given data record is a valid record.
	 * @param $dataLine a structure containing the data record as it will be saved to the DB.
	 * @return true (by default) if the line is valid or false if theres some problem.
	 */
	protected function isValidDataRecord($dataLine) {
		$itOnUsDate = date_create(self::ITS_ON_GOLAN_DATE);
		$dataLineCreateDate = date_create_from_format("YmdHis", $dataLine['call_start_dt']);

		return $dataLineCreateDate && intval($itOnUsDate->diff($dataLineCreateDate)->format("%r%a")) >= 0;
	}

}
