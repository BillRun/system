<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator class for SMSc records
 *
 * @package  calculator
 */
class Billrun_Calculator_Rate_Smsc extends Billrun_Calculator_Rate_Sms {

	static protected $type = 'smsc';

	/**
	 * This array  hold checks that each line  is required to match i order to get rated for customer rate.
	 * @var array 'field_in_cdr' => 'should_match_this_regex'
	 */
	protected $legitimateValues = array(
		'smsc' => array('cause_of_terminition' => "^100$", 'record_type' => '^2$', 'calling_msc' => "^0*9725[82]"),
		'smpp' => array('record_type' => '2'),
	);
	
	
	public function __construct($options = array()) {
		parent::__construct($options);
		if (isset($options['calculator']['legitimate_values_smpp']) && $options['calculator']['legitimate_values_smpp']) {
			$this->legitimateValues['smpp'] = $options['calculator']['legitimate_values_smpp'];
		}
	}
	
	
	

	/**
	 * @see Billrun_Calculator::isLineLegitimate
	 */
	public function isLineLegitimate($line) {
		return ($line['type'] == 'smsc');
	}

	/**
	 * Check if a given line should be rated.
	 * @param type $row
	 * @return type
	 */
	protected function shouldLineBeRated($row) {
		//return  $row['record_type'] == '1' && $row["cause_of_terminition"] == "100" && preg_match("/^0*9725[82]/",$row["calling_msc"]) ;
		
		if (!in_array($row['record_type'], ['1','2','4'])){
			Billrun_Factory::log()->log($row['record_type'] . ' is Illegal value for record_type, line: ' . $row['stamp'] , Zend_Log::ALERT);
			return false;
		}
		if ((($row['record_type'] == 4) && ($row['org_protocol'] != 0)) || (($row['org_protocol'] == 0) && ($row['record_type'] != 4))){
			Billrun_Factory::log()->log($row['record_type'] .' and' . $row['org_protocol'] .' is Illegal combination of values for record_type and org_protocol fields, line: ' . $row['stamp'] , Zend_Log::ALERT);
			return false;
		}	
		if (!in_array($row['org_protocol'], ['0','1','3'])){
			Billrun_Factory::log()->log($row['org_protocol'] . ' is Illegal value for org_protocol, row: ' . $row['stamp'] , Zend_Log::ALERT);
			return false;
		}
		if ($row['org_protocol'] == '0'){
			return false;
		}
		if ($row['org_protocol'] == '1') {  //smsc
			foreach ($this->legitimateValues['smsc'] as $key => $value) {
				if (is_array($value)) {
					foreach ($value as $regex) {
						if (!preg_match("/" . $regex . "/", $row[$key])) {
							return false;
						}
					}
				} else if (!preg_match("/" . $value . "/", $row[$key])) {
					return false;
				}
			}
		} else if ($row['org_protocol'] == '3') {  //smpp
			foreach ($this->legitimateValues['smpp'] as $key => $value) {
				if (!(is_array($value) && in_array($row[$key], $value) || $row[$key] == $value )) {
					return false;
				}
			}
		}

		return true;
	}

}
