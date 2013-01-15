<?php
/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing 013 Responder file processor
 *
 * @package  Billing
 * @since    1.0
 */
class Billrun_Responder_014 extends Billrun_Responder_Base_Ilds {

	protected $linesErrors = 0;

	public function __construct(array $params = array()) {
		parent::__construct($params);
		self::$type = '014';


		$this->data_structure = array(
			'record_type' => "%1s",
			'caller_msi' => "%03s",
			'caller_phone_no' => '%-11s',
			'called_no' => '%-28s',
			'call_start_dt' => '%14s',
			//'call_start_tm' => '%6s',
			'actual_call_dur' => '%06s',
			'chrgbl_call_dur' => '%06s',
			'units' => '%4s',
			'call_charge_sign' => '%1s',
			'call_charge' => '%11s',
			'collection_ind' => '%1s',
			'collection_ind2' => '%1s',
			'provider_subscriber_type' => '%1s',
			'record_status' => '%02s',
// 			'sequence_no' => '%06s',
// 			'correction_code' => '%2s',
// 			'call_type' => '%2s',
			'filler' => '%87s',
		);


		$this->header_structure = array(
			'record_type' => '%1s',
			'file_type' => '%14s',
			'sending_company_id' => '%3s',
			'receiving_company_id' => '%3s',
			'sequence_no' => '%5s',
			'file_creation_date' => '%8s',
			'file_creation_time' => '%6s',
			'file_received_date' => '%8s',
			'file_received_time' => '%6s',
			'file_status' => '%2s',
		);

		$this->trailer_structure = array(
			'record_type' => '%1s',
			'file_type' => '%14s',
			'sending_company_id' => '%3s',
			'receiving_company_id' => '%3s',
			'sequence_no' => '%5s',
			'file_creation_date' => '%8s',
			'file_creation_time' => '%6s',
			'file_received_date' => '%8s',
			'file_received_time' => '%6s',
			'total_charge_sign' => '%1s',
			'total_charge' => '%16s',
			'total_rec_no' => '%6s',
			'total_valid_rec_no' => '%6s',
			'total_err_rec_no' => '%6s',
		);
	}

	protected function processFileForResponse($filePath, $logLine) {
		$tmpLogLine = $logLine->getRawData();
		$unprocessDBLines = $this->db->getCollection(self::lines_table)->query()->notExists('billrun')->equals('file', $tmpLogLine['file']);
		//run only if theres problematic lines in the file.
		if ($unprocessDBLines->count() == 0) {
			return false;
		}

		return parent::processFileForResponse($filePath, $logLine);
	}

	protected function processLineErrors($dbLine) {
		if(!isset($dbLine['billrun']) || !$dbLine['billrun']) {
			$dbLine['record_status'] = '02';
		}
		return $dbLine;
	}

	protected function getResponseFilename($receivedFilename, $logLine) {
		$responseFilename = preg_replace("/\WMBZ\W/", "OUR", $receivedFilename);
		$responseFilename = preg_replace("/\WGTC\W/", "MBZ", $responseFilename);
		$responseFilename = preg_replace("/\WOUR\W/", "GTC", $responseFilename);
		return $responseFilename;
	}

}
