<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator class for SMS records
 * (TODO  refactor  this to there different classes (MMSC/SMPP/SMSC) and then abstract it)
 * @package  calculator
 * @since    0.5
 */
abstract class Billrun_Calculator_Rate_Sms extends Billrun_Calculator_Rate {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'sms';

	/**
	 * regex filters to do on the  number  that was received from the CDR.
	 *
	 * @var string
	 */

	/**
	 * This array holds translation map that is needed inorder to match the numbers provided from the switch withthe values in the rates.
	 * @var array 'regex_to_look_for_in_number' => 'replacment_string'
	 */
	protected $prefixTranslation = array(0 => array('^0+' => ''), 1 => array('[^\d]' => ''));

	public function __construct($options = array()) {
		parent::__construct($options);
		if (isset($options['calculator']['prefix_translation'])) {
			$this->prefixTranslation = $options['calculator']['prefix_translation'];
		}
		ksort($this->prefixTranslation);
		$this->loadRates();
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineVolume
	 * @deprecated since version 2.9
	 */
	protected function getLineVolume($row) {
		return 1;
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineUsageType
	 * @deprecated since version 2.9
	 */
	protected function getLineUsageType($row) {
		return $row['type'] == 'mmsc' ? 'mms' : 'sms';
	}

	/**
	 * Check if a given line should be rated.
	 * @param type $row
	 * @return type
	 */
	protected function shouldLineBeRated($row) {
		return ($row['type'] == 'smpp' && $row['record_type'] == '1') || // also remove these numbers before commiting
			($row['type'] == 'smsc' && $row['record_type'] == '1' && $row["cause_of_terminition"] == "100" && preg_match("/^0*9725[82]/", $row["calling_msc"]) ) ||
			($row['type'] == 'mmsc' && ('S' == $row['action']) && $row['final_state'] == 'S' && preg_match('/^\+\d+\/TYPE\s*=\s*.*telecom/', $row['mm_source_addr']));
	}

	/**
	 * Get the associate rate object for a given CDR line.
	 * @param $row the CDR line to get the for.
	 * @param $usage_type the CDR line  usage type (SMS/Call/etc..)
	 * @param $type CDR type
	 * @param $tariffCategory rate category
	 * @param $filters array of filters used to find the rate
	 * @return the Rate object that was loaded  from the DB  or false if the line shouldn't be rated.
	 */
	protected function getLineRate($row, $usaget, $type, $tariffCategory, $filters) {
		if ($this->shouldLineBeRated($row)) {
			$matchedRate = $this->rates['UNRATED'];
			$called_number = $this->extractNumber($row);
			$line_time = $row['urt'];
			$called_number_prefixes = Billrun_Util::getPrefixes($called_number);
			foreach ($called_number_prefixes as $prefix) {
				if (isset($this->rates[$prefix])) {
					foreach ($this->rates[$prefix] as $rate) {
						if (isset($rate['rates'][$row['usaget']]) && (!isset($rate['params']['fullEqual']) || $prefix == $called_number)) {
							if ($rate['from'] <= $line_time && $rate['to'] >= $line_time) {
								$matchedRate = $rate;
								break 2;
							}
						}
					}
				}
			}
			return $matchedRate;
		} else {
			return false;
		}
	}

	/**
	 * @see Billrun_Calculator::isRateValid()
	 */
	protected function isRateValid($rate) {
		return preg_match("/^(?!AC_|VF_|LEFTOVER_AC_TO_VF_)/", $rate['key']);
	}

	/**
	 * @see Billrun_Calculator::isLineLegitimate
	 */
	public function isLineLegitimate($line) {
		return $line['type'] == 'smsc' || $line['type'] == 'mmsc' || $line['type'] == 'smpp';
	}

	/**
	 * Extract the number from the cdr line using a given checks and transformation  inorder to use it for  finding its rate.
	 * @param type $row the cdr line
	 * @return type
	 */
	protected function extractNumber($row) {
		$str = (isset($row['called_number']) ? $row['called_number'] : "");

		foreach ($this->prefixTranslation as $regex_group) {
			foreach ($regex_group as $from => $to) {
				if (preg_match("/" . $from . "/", $str)) {
					$str = preg_replace("/" . $from . "/", $to, $str);
					break;
				}
			}
		}

		return $str;
	}

}
