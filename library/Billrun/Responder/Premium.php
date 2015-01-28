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
class Billrun_Responder_Premium extends Billrun_Responder_Base_Ilds {


	public function __construct(array $params = array()) {
		parent::__construct($params);
		self::$type = 'premiumHot';


$this->data_structure = array(
			'record_type' => "%1s",
			'call_type' => "%02s",
			'caller_phone_no' => "%010s",
			'called_no' => "% -18s",
			'phone_pickup_dt' => "%14s",
			'call_start_dt' => "%14s",
			'call_end_dt' => "%14s",
			'pickup_to_hangup_dur' => "%6s",
			'call_dur' => "%6s",
			'pricing_code' => "%1s",
			'chrgbl_call_dur' => "%6s",
			'first_price_sign' => "%1s",
			'first_price' => "%11s",
			'second_price_sign' => "%1s",
			'second_price' => "%11s",
			'premium_price_sign' => "%1s",
			'premium_price' => "%11s",
			'collection_ind' => "%2s",
			'filler' => "%80s",
		);
		//not used, updateHeader() and updateTrailer() are used instead to CREATE header and trailer
//		$this->header_structure = array(
//			'record_type' => "%1s",
//			'file_type' => "% -15s",
//			'receiving_company_id' => "% -10s",
//			'sending_company_id' => "% -10s",
//			'sequence_no' => "%06s",
//			'file_creation_date' => "%12s",
//		);
//
//
//		$this->trailer_structure = array(
//			'record_type' => "%1s",
//			'file_type' => "% -15s",
//			'receiving_company_id' => "% -10s",
//			'sending_company_id' => "% -10s",
//			'sequence_no' => "%06s",
//			'file_creation_date' => "%12s",
//			'total_phone_number' => "%015s", // WTF?!
//			'total_charge_sign_from_operator' => "%1s",
//			'total_charge_from_operator' => "%015s",
//			'total_charge_sign' => "%1s",
//			'total_charge' => "%015s",
//			'total_rec_no' => "%6s",
//			'total_err_rec_no' => "%6s",
//			'filler' => "%80s",
//		);
		
	}


	protected function updateHeader($line, $logLine) {
		$header = "";
		$header.=sprintf("%1s",$logLine['header']['record_type']);
		$header.=sprintf("% -15s",$logLine['header']['file_type'].'_R');
		$header.=sprintf("% -10s",$logLine['header']['receiving_company_id']); //the receiving company in the original file is the sending in the response 
		$header.=sprintf("% -10s",$logLine['header']['sending_company_id']);   // and vice versa
		$header.=sprintf("%06s",$logLine['header']['sequence_no']);   
		$now = date("YmdHi"  , strtotime('now'));
		$header.=sprintf("%12s",$now);   
		$header.=sprintf("%12s",$now);		
		$header.=sprintf("%02s",$this->getHeaderStateCode($logLine));	
		$header.=$logLine['header']['filler'];
		return $header;
	}

	protected function updateTrailer($logLine) {
		
		$trailer = "";
		$trailer.=sprintf("%1s",$logLine['trailer']['record_type']);
		$trailer.=sprintf("% -15s",$logLine['trailer']['file_type'].'_R');
		$trailer.=sprintf("% -10s",$logLine['trailer']['receiving_company_id']); //the receiving company in the original file is the sending in the response 
		$trailer.=sprintf("% -10s",$logLine['trailer']['sending_company_id']);   // and vice versa
		$trailer.=sprintf("%06s",$logLine['trailer']['sequence_no']);   
		$now = date("YmdHi"  , strtotime('now'));
		$trailer.=sprintf("%12s",$now);
		$trailer.=sprintf("%1s",$logLine['trailer']['total_charge_sign_from_operator']);
		$trailer.=sprintf("%15s",$logLine['trailer']['total_charge_from_operator']);
		$trailer.=sprintf("%1s",$logLine['trailer']['total_charge_sign']);
		$trailer.=sprintf("%15s",$logLine['trailer']['total_charge']);
		$trailer.=sprintf("%06s",$logLine['trailer']['total_rec_no']);
		$trailer.=sprintf("%06s",$logLine['trailer']['total_err_rec_no']);
		$trailer.=$logLine['trailer']['filler'];
		return $trailer;

	}

	protected function processLineErrors($dbLine) {
		if(!isset($dbLine['subscriber_id']) || !isset($dbLine['account_id'])) {
			$dbLine['record_status'] = '02';
		}
		if(!is_numeric($dbLine['chrgbl_call_dur']) || ($dbLine['chrgbl_call_dur'] <= '0')) {
			$dbLine['record_status'] = '07';
		}
		if(!is_numeric($dbLine['price_customer']) || ($dbLine['price_customer'] <= 0)) {
			$dbLine['record_status'] = '11';
		}
		if($dbLine['unified_record_time']->sec < strtotime('-3 months') ) {
			$dbLine['record_status'] = '16';
		}
		return $dbLine;
	}

	protected function getResponseFilename($receivedFilename, $logLine) {
		$responseFilename = preg_replace("/_CDR_/i", "_CDR_R_", $receivedFilename);
		return $responseFilename;
	}
	
	protected function	getHeaderStateCode($logLine) {
		if($logLine['header']['file_type'] != "CDR") {
			return 1;
		}
		if( !in_array(strtolower($logLine['header']['sending_company_id']) , Billrun_Factory::config()->getConfigValue('premium.provider_ids')) ) {
			return 2;
		}
		if($logLine['header']['receiving_company_id'] != "GOL") {
			return 3;
		}
		if(!is_numeric($logLine['header']['sequence_no']) ) {
			return 4;
		}
		if(!date_create_from_format("YmdHi",$logLine['header']['file_creation_date']) ) {
			return 5;
		}		
		//TOD add detection of  phone number sum and record sum errors
		return 0;
	}

}
