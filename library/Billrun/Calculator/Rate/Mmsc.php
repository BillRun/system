<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing calculator class for SMSc records
 *
 * @package  calculator
 */
class Billrun_Calculator_Rate_Mmsc extends Billrun_Calculator_Rate_Sms {
	/**
	 * @see Billrun_Calculator_Rate::getLineUsageType
	 */
	protected function getLineUsageType($row) {
		return  'mms';
	}

	/**
	 * @see Billrun_Calculator::isLineLegitimate
	 */
	public function isLineLegitimate($line) {
		return  $line['type'] == 'mmsc' ;
	}
	
	/**
	 * Check if a given line should be rated.
	 * @param type $row
	 * @return type
	 */
	protected function shouldLineBeRated($row) {
		return  ('S' == $row['action']) && $row['final_state'] == 'S' && preg_match('/^\+\d+\/TYPE\s*=\s*.*golantelecom/', $row['mm_source_addr']);
	}
	
	protected function extractNumber($row) {
		return preg_replace('/[^\d]/', '', preg_replace('/^0+/', '', ($row['recipent_addr'])));
	}
}
