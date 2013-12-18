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
class Billrun_Calculator_Rate_Smsc extends Billrun_Calculator_Rate_Sms {
	
	/**
	 * This array holds translation map that is needed inorder to match the numbers provided from the switch withthe values in the rates.
	 * @var array 'regex_to_look_for_in_number' => 'replacment_string'
	 */
	protected $prefixTranslation = array();
	
	/**
	 * This array  hold checks that each line  is required to match i order to get rated for customer rate.
	 * @var array 'field_in_cdr' => 'should_match_this_regex'
	 */
	protected $legitimateValues = array(
									'cause_of_terminition' => "100",
									'record_type' => '1',
									'calling_msc' => "^0*9725[82]",
								);
	
	public function __construct($options = array()) {
		parent::__construct($options);
		
		if(isset($options['calculator']['prefix_translation'])) {
			$this->prefixTranslation = $options['calculator']['prefix_translation'];
		}
	}
	/**
	 * @see Billrun_Calculator::isLineLegitimate
	 */
	public function isLineLegitimate($line) {
		return $line['type'] == 'smsc' ;
	}
	
	/**
	 * Check if a given line should be rated.
	 * @param type $row
	 * @return type
	 */
	protected function shouldLineBeRated($row) {
		//return  $row['record_type'] == '1' && $row["cause_of_terminition"] == "100" && preg_match("/^0*9725[82]/",$row["calling_msc"]) ;
		foreach ($this->legitimateValues as $key => $value) {
			if( is_array($value) ) {				
				foreach ($value as $regex) {
					if(!preg_match("/".$regex."/", $row[$key])) {
						return false;
					}
				}
			} else if(!preg_match("/".$value."/", $row[$key])) {
					return false;
			}
		}
		return true;
	}
	/**
	 * @see Billrun_Calculator_Rate_Sms::extractNumber($row)
	 */
	protected function extractNumber($row) {
		$str =  $row['called_number'];
		
		foreach ($this->prefixTranslation as $from => $to ) {
			//Billrun_Factory::log()->log("Checking a match to $from at $str", Zend_Log::DEBUG);
			if(preg_match("/".$from."/", $str)) {
				Billrun_Factory::log()->log("Found a match to $from translating it $to", Zend_Log::DEBUG);
				$str = preg_replace("/".$from."/", $to, $str);
			}
		}
		
		foreach ($this->legitimateNumberFilters as $filter) {
			$str = preg_replace($filter, '', $str);
		}		
		return $str;
		//return preg_replace('/[^\d]/', '', preg_replace('/^0+/', '', ($row['type'] != 'mmsc' ? $row['called_msc'] : $row['recipent_addr'])));
	}
}
