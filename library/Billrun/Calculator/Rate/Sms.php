<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
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
	
	protected $roaming_sms_rates;

	public function __construct($options = array()) {
		parent::__construct($options);
		if (isset($options['calculator']['prefix_translation'])) {
			$this->prefixTranslation = $options['calculator']['prefix_translation'];
		}
		ksort($this->prefixTranslation);
		$this->loadRates();
		$this->loadRoamingSmsRates();
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineVolume
	 */
	protected function getLineVolume($row, $usage_type) {
		return 1;
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineUsageType
	 */
	protected function getLineUsageType($row) {
		return $row['usaget'];
	}

	/**
	 * Check if a given line should be rated.
	 * @param type $row
	 * @return type
	 */
	protected function shouldLineBeRated($row) {
		return ($row['type'] == 'smpp' && $row['record_type'] == '2') || // also remove these numbers before commiting
				($row['type'] == 'smsc' && $row['record_type'] == '2' && $row["cause_of_terminition"] == "100" && preg_match("/^0*9725[82]/", $row["calling_msc"]) ) ||
				($row['type'] == 'mmsc' && ('S' == $row['action']) && $row['final_state'] == 'S' && (preg_match('/^\+\d+\/TYPE\s*=\s*.*golantelecom/', $row['mm_source_addr']) || (isset($row['mms_from_cellcom_mmsc']) && $row['mms_from_cellcom_mmsc'] === 'GOLAN')));
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineRate
	 */
	protected function getLineRate($row, $usage_type) {
		if ($this->shouldLineBeRated($row)) {
			$line_time = $row['urt'];
			$matchedRate = $this->rates['UNRATED'];
			$without_called_msc = true;
			$called_msc = $row['called_msc'];
			$called_number = $this->extractNumber($row);
			$called_number_prefixes = Billrun_Util::getPrefixes($called_number);
			if ($usage_type == 'sms' && !preg_match('/^0*972/', $called_number)) {
				$without_called_msc = false;
			}
			foreach ($called_number_prefixes as $prefix) {
				if (isset($this->rates[$prefix])) {
					foreach ($this->rates[$prefix] as $rate) {
						if (isset($rate['rates'][$usage_type]) && (!isset($rate['params']['fullEqual']) || $prefix == $called_number)) {
							if ($rate['from'] <= $line_time && $rate['to'] >= $line_time) {
								if ($without_called_msc || $this->checkCalledMsc($rate, $called_msc)){
									$matchedRate = $rate;
									break 2;
								}	
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
	
	protected function loadRoamingSmsRates(){
		$rates_coll = Billrun_Factory::db()->ratesCollection();
		$rates = Billrun_Factory::db()->ratesCollection()->query($this->rates_query)->cursor();
		$this->roaming_sms_rates = array();
		foreach ($rates as $rate) {
				$rate->collection($rates_coll);
				if (isset($rate['kt_prefixes'])) {
					foreach ($rate['kt_prefixes'] as $prefix) {
						$this->roaming_sms_rates[$prefix][] = $rate;
					}
				} else if ($rate['key'] == 'UNRATED') {
					$this->roaming_sms_rates['UNRATED'] = $rate;
				}
		}
	}
	
	
	protected function getAdditionalProperties() {
		return array_merge(array('alpha3'), parent::getAdditionalProperties());
	}
	
	
	protected function checkCalledMsc($rate, $called_msc) {
		if (isset($rate['params']['called_msc'])){
			$called_msc_regex = $rate['params']['called_msc'];
		} else {
			return true;
		}
		return preg_match($called_msc_regex, $called_msc);
	}
	
}
