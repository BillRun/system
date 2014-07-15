<?php
/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing 017 Responder file processor
 *
 * @package  Billing
 * @since    1.0
 * TODO ! ACTUALLY IMPLEMENT!! THERES CURRENTLY NO SPEC'S FOR THIS ! TODO
 */
class Billrun_Responder_017 extends Billrun_Responder_Base_Ilds {

	public function __construct(array $params = array()) {
		parent::__construct($params);
		self::$type = '017';

		$this->data_structure = array(
			'record_type' => '%1s',
			'operator_code' => '%3s',
			'call_type' => '%1s',
			'caller_phone_no' => '%010s',
			'call_start_dt' => '%14s',
			'call_end_dt' => '%14s',
			'called_no' => '%-16s',
			'chrgbl_call_dur' => '%06s',
			'call_charge' => '%011s',
			'correction_code' => '%02s',
		);


		$this->header_structure = array(
			'filename' => '%11s',
			'record_type' => '%1s',
			'file_creation_date' => '%8s',
			'file_received_time' => '%6s',
		);

		$this->trailer_structure = array(
			'filename' => '%11s',
			'record_type' => '%1s',
			'file_creation_date' => '%8s',
			'file_received_time' => '%6s',
			'file_size' => '%9s',
			'line_count' => '%9s',
			'total_charge' => '%17s',
		);
	}

	protected function processLineErrors($dbLine) {
		if(!isset($dbLine['billrun']) || !$dbLine['billrun']) {
			return false;
		}
		return $dbLine;
	}

	protected function getResponseFilename($receivedFilename, $logLine) {
		$responseFilename = preg_replace("/_CDR_/i", "_CDR_R_",
								preg_replace("/_OUR_/i", "_GLN_",
									preg_replace("/_GLN_/i", "_HOT_", 
										preg_replace("/_HOT_/i", "_OUR_", $receivedFilename)
									)
								)
							);
		return $responseFilename;
	}

}
