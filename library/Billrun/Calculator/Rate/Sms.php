<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
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
	 * Write the calculation into DB.
	 * @param $row the line CDR to update. 
	 */
	public function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteRow', array($row, $this));

		$current = $row->getRawData();
		$usage_type = $this->getLineUsageType($row);
		$volume = $this->getLineVolume($row, $usage_type);
		$rate = $this->getLineRate($row, $usage_type);
		$added_values = array(
			'usaget' => $usage_type,
			'usagev' => $volume,
			$this->ratingField => $rate ? $rate->createRef() : $rate,
		);
		$newData = array_merge($current, $added_values);
		$row->setRawData($newData);

		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteRow', array($row, $this));
		return true;
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
		return $row['type'] == 'mmsc' ? 'mms' : 'sms';
	}

	/**
	 * Check if a given line should be rated.
	 * @param type $row
	 * @return type
	 */
	protected function shouldLineBeRated($row) {
		return ($row['type'] == 'smpp' && $row['record_type'] == '1' && in_array($row['called_number'], array('000000000002020', '000000000006060', '000000000007070', '000000000005060', '000000000002040'))) ||
			($row['type'] == 'smsc' && $row['record_type'] == '1' && $row["cause_of_terminition"] == "100" && preg_match("/^0*9725[82]/", $row["calling_msc"]) ) ||
			($row['type'] == 'mmsc' && ('S' == $row['action']) && $row['final_state'] == 'S' && preg_match('/^\+\d+\/TYPE\s*=\s*.*golantelecom/', $row['mm_source_addr']));
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineRate
	 */
	protected function getLineRate($row, $usage_type) {
		if ($this->shouldLineBeRated($row)) {
			$matchedRate = false;
			$called_number = $this->extractNumber($row);
			$line_time = $row['urt'];
			$called_number_prefixes = $this->getPrefixes($called_number);
			foreach ($called_number_prefixes as $prefix) {
				if (isset($this->rates[$prefix])) {
					foreach ($this->rates[$prefix] as $rate) {
						if (isset($rate['rates'][$usage_type])) {
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
