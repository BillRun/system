<?php
/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing Remote Files responder class
 *
 * @package  Billing
 * @since    1.0
 * TODO ! ACTUALLY IMPLEMENT!! THERESCURRENTLY NO SPEC'S FOR THIS ! TODO
 */
class Billrun_Responder_018 extends Billrun_Responder_Base_Ilds {

	protected $linesErrors = 0;

	public function __construct(array $params = array()) {
		parent::__construct($params);
		self::$type = '018';

		$this->data_structure = array(
			'record_type' => '%1s',
			'call_type' => '%2s',
			'caller_phone_no' => '%10s',
			'called_no' => '%-18s',
			'call_start_dt' => '%14s',
			'call_end_dt' => '%14s',
			'actual_call_dur' => '%6s',
			'chrgbl_call_dur' => '%6s',
			'call_charge_sign' => '%1s',
			'call_charge' => '%11s',
			'collection_ind' => '%1s',
			'record_status' => '%2s',
			'sequence_no' => '%6s',
			'correction_code' => '%2s',
			'filler' => '%-88s',
		);


		$this->header_structure = array(
			'record_type' => '%1s',
			'file_type' => '%3s',
			'sending_company_id' => '%4s',
			'receiving_company_id' => '%4s',
			'sequence_no' => '%6s',
			'file_creation_date' => '%14s',
			'file_received_date' => '%14s',
			'file_status' => '%2s',
			'version_no' => '%2s',
			'filler' => '%140s',
		);

		$this->trailer_structure = array(
			'record_type' => '%1s',
			'file_type' => '%3s',
			'sending_company_id' => '%-4s',
			'receiving_company_id' => '%4s',
			'sequence_no' => '%6s',
			'file_creation_date' => '%14s',
			'total_charge_sign' => '%1s',
			'total_charge' => '%15s',
			'total_rec_no' => '%6s',
			'total_err_rec_no' => '%6s',
			'filler' => '%-122s',
		);
	}
	protected function processErrorLine($dbLine) {
		return  false;
	}

	protected function getResponseFilename($receivedFilename, $logLine) {
		return $receivedFilename;
	}

}
