<?php
/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing Remote Files responder class
 *
 * @package  Billing
 * @since    0.5
 */
class Billrun_Responder_012 extends Billrun_Responder_Base_Ilds {


	public function __construct(array $params = array()) {
		parent::__construct($params);
		self::$type = '012';

		$this->data_structure = array(
			'record_type' => "%1s",
			'call_type' => "%02s",
			'caller_phone_no' => "%09s",
			'call_start_dt' => "%014s",
			'chrgbl_call_dur' => "%06s",
			'called_no' => "%-24s",
			'country_desc' => "%-20s",
			'collection_ind' => "%1s",
			'record_id' => "%22s",
			'charge_code' => "%1s",
			'call_charge_sign' => "%1s",
			'call_charge' => "%015s",
			'record_status' => "%02s",
		);

		$this->header_structure = array(
			'record_type' => "%1s",
			'file_type' => "%10s",
			'receiving_company_id' => "%10s",
			'sending_company_id' => "%10s",
			'sequence_no' => "%6s",
			'file_creation_date' => "%12s",
		);

		$this->trailer_structure = array(
			'record_type' => "%1s",
			'file_type' => "%10s",
			'receiving_company_id' => "%10s",
			'sending_company_id' => "%10s",
			'sequence_no' => "%6s",
			'file_creation_date' => "%12s",
			'total_phone_number' => "%15s", // WTF?!
			'total_charge_sign' => "%1s",
			'total_charge' => "%015s",
			'total_rec_no' => "%6s",
			'total_err_rec_no' => "%6s",
		);
	}

	protected function updateHeader($line, $logLine) {
		$line = parent::updateHeader($line, $logLine);
		$line = substr($line, 0, 49);
		$now = date_create();
		$line.=$now->format("YmdHi");
		$line.= sprintf("%02s",$this->getHeaderStateCode($line, $logLine)); //TODO add problem detection.
		$line = $this->switchNamesInLine("GLN", "KVZ", $line);
		$line = preg_replace("/MABAL  /", "MABAL_R", $line);
		return $line;
	}

	protected function updateTrailer($logLine) {
		$logLine['file_type'] = "MABAL_R";
		$logLine['total_charge'] = $this->totalChargeAmount;
		$logLine['total_rec_no'] =  $this->linesCount;
		$logLine['total_err_rec_no'] =  $this->linesErrors;
		$line = parent::updateTrailer($logLine);
//		$line.= sprintf("%015s", $this->totalChargeAmount);
//		$line.= sprintf("%6s", $this->linesCount);
//		$line.= sprintf("%6s", $this->linesErrors);
		return $line;
	}

	protected function processLineErrors($dbLine) {
		if(!isset($dbLine['billrun']) || !$dbLine['billrun']) {
			$dbLine['record_status'] = '02';
		}
		return $dbLine;
	}

	protected function getResponseFilename($receivedFilename, $logLine) {
		$responseFilename = preg_replace("/_MABAL_/i", "_MABAL_R_",
								preg_replace("/_OUR_/i", "_GLN_",
									preg_replace("/_GLN_/i", "_KVZ_", 
										preg_replace("/_KVZ_/i", "_OUR_", $receivedFilename)
									)
								)
							);
		return $responseFilename;
	}
	
	protected function getHeaderStateCode($headerLine,$logLine) {
		if(substr($headerLine, 6,5) != "MABAL") {
			return 1;
		}
		if(substr($headerLine, 18,3) != "KVZ") {
			return 2;
		}
		if(substr($headerLine, 28,3) != "GLN") {
			return 3;
		}
		if(!is_numeric(substr($headerLine, 31,6)) ) {
			return 4;
		}
		if(!date_create_from_format("YmdHi",substr($headerLine, 37,12)) ) {
			return 5;
		}		
		//TOD add detection of  phone number sum and record sum errors
		return 0;
	}

}
