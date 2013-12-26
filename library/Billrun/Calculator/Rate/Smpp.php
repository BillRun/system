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
class Billrun_Calculator_Rate_Smpp extends Billrun_Calculator_Rate_Sms {
	
	static protected $type = 'smpp';
	
	protected $legitimateValues = array(
//		'cause_of_terminition' => "100",
		'record_type' => '1',
		'called_number' => array('000000000002020', '000000000006060', '000000000007070', '000000000005060', '000000000002040'),
	);

	public function __construct($options = array()) {
		parent::__construct($options);
		if (isset($options['calculator']['legitimate_values']) && $options['calculator']['legitimate_values']) {
			$this->legitimateValues = $options['calculator']['legitimate_values'];
		}
	}

	/**
	 * Check if a given line should be rated.
	 * @param type $row
	 * @return type
	 */
	protected function shouldLineBeRated($row) {
		foreach ($this->legitimateValues as $key => $value) {
			if (!(is_array($value) && in_array($row[$key], $value) || $row[$key] == $value )) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @see Billrun_Calculator::isLineLegitimate
	 */
	public function isLineLegitimate($line) {
		return $line['type'] == 'smpp';
	}

}
