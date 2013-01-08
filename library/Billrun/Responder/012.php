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
 */
class Billrun_Responder_012 extends Billrun_Responder_Base_Ilds {

	protected $linesErrors = 0;

	public function __construct( $options = false ) {
		parent::__construct( $options );
		$this->type = "012";

		$this->data_structure = array(
			'record_type' => "%1s",
			'call_type' => "%02s",
			'caller_phone_no' => "%09s",
			'call_start_dt' => "%014s",
			'chrgbl_call_dur' => "%06s",
			'called_no' => "%-24s",
			'country_desc' => "%-20s",
			'collection_ind' => "%1s",
			'record_id' =>"%22s",
			'charge_code' => "%1s",
			'call_charge_sign' => "%1s",
			'call_charge' => "%015s",
			'record_status' => "%02s",
		);

		$this->header_structure = array(
			'record_type' => "%1s",
			'file_type' => "%10s",
			'sending_company_id' => "%10s",
			'receiving_company_id' => "%10s",
			'sequence_no' => "%6s",
			'file_creation_date' => "%12s",
		);

		$this->trailer_structure = array(
			'record_type' => "%1s",
			'file_type' => "%10s",
			'sending_company_id' => "%10s",
			'receiving_company_id' => "%10s",
			'sequence_no' => "%6s",
			'file_creation_date' => "%12s",
			'total_phone_number' => "%15s", // WTF?!
			'total_charge_sign' => "%1s",
			//'total_charge' => "%15s",
			//'total_rec_no' => "%6s",
		);
	}

	protected function updateHeader($line,$logLine) {
		$line = parent::updateHeader($line,$logLine);
		$line = substr($line, 0, 49);
		$now = date_create();
		$line.=$now->format("YmdHi");
		$line.="00"; //TODO add problem detection.

		return $line;

	}

	protected function updateTrailer($logLine) {
		$line = parent::updateTrailer($logLine);
		$line.=  sprintf("%015s",$this->totalChargeAmount);
		$line.=  sprintf("%06s",$this->linesCount);
		$line.=  sprintf("%06s",$this->linesErrors);
		return $line;
	}

	function processErrorLine($dbLine) {
		$dbLine['record_status'] = '02';
		return  $dbLine;
	}
}
