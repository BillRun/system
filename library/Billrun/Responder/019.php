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
class Billrun_Responder_019 extends Billrun_Responder_Base_Ilds {

	protected $phoneNumbersSum = 0;
	
	public function __construct(array $params = array()) {
		parent::__construct($params);
		self::$type = '019';

		$this->data_structure = array(
			'record_type' => '%1s',
			'call_type' => '%2s',
			'caller_phone_no' => '%10s',
			'called_no' => '%-18s',
			'call_start_dt' => '%14s',
			'call_end_dt' => '%14s',
			'chrgbl_call_dur' => '%06s',
			'call_charge_sign' => '%1s',
			'call_charge' => '%11s',
			'collection_ind' => '%2s',
			'record_status' => '%2s',
		);


		$this->header_structure = array(
			'record_type' => '%1s',
			'file_type' => '%-15s',
			'sending_company_id' => '%-10s',
			'receiving_company_id' => '%-10s',
			'sequence_no' => '%6s',
			'file_creation_date' => '%12s',
			'file_sending_date' => '%12s',
			'file_status' => '%2s',
			'filler' => '%187s',
		);

		$this->trailer_structure = array(
			'record_type' => '%1s',
			'file_type' => '%-15s',
			'sending_company_id' => '%-10s',
			'receiving_company_id' => '%-10s',
			'sequence_no' => '%6s',
			'file_creation_date' => '%12s',
			'sum_of_number' => '%015s', 
			'total_charge_sign2' => '%1s',
			'total_charge2' => '%015s',
			'total_charge_sign' => '%1s',
			'total_charge' => '%015s',
			'total_err_rec_no' => '%06s',
		);
	}
	
	protected function updateHeader($line, $logLine) {
//		$logLine['file_type'] = 'CDR_R';
//		$logLine['file_sending_date'] =  date_create()->format("YmdHi");
//		$logLine['file_status'] = '00'; //TODO
 		$line = parent::updateHeader($line, $logLine);
		$line = substr($line, 0, 54);
		$now = date_create();
		$line.=$now->format("YmdHi");
		$line.= $this->getHeaderErrors($logLine);
		$line = preg_replace("/^HCDR  /", "HCDR_R", $line);
		return $line;
	}
	
	protected function updateTrailer($logLine) {
		$logLine['file_type'] = "CDR_R";
		$logLine['total_charge']=$this->totalChargeAmount;
		$logLine['total_err_rec_no']= $this->linesErrors;
		$logLine['sum_of_number']= $this->phoneNumbersSum*1000;
		$line = parent::updateTrailer($logLine);
		return $line;
	}
	
	protected function updateLine($dbLine, $logLine) {
		$this->phoneNumbersSum += intval(substr($dbLine['caller_phone_no'],3));
		return parent::updateLine($dbLine, $logLine);
	}
	
	protected function processLineErrors($dbLine) {
		if(!isset($dbLine['billrun']) || !$dbLine['billrun']) {
				$dbLine['record_status'] = '02';
		}
		return  $dbLine;
	}

	protected function processFileForResponse($filePath, $logLine) {
		$this->phoneNumbersSum= 0;
		return parent::processFileForResponse($filePath, $logLine);
	}
	
	protected function getResponseFilename($receivedFilename, $logLine) {
		$responseFilename = preg_replace("/_CDR_/i", "_CDR_R_",
								preg_replace("/_OUR_/i", "_GOL_",
									preg_replace("/_GOL_/i", "_TLZ_", 
										preg_replace("/_TLZ_/i", "_OUR_", $receivedFilename)
									)
								)
							);
		return $responseFilename;
	}


	protected function getHeaderErrors($logLine) {
		return "00"; //TODO require reporocessing the file should be done in the processor.
	}
}
