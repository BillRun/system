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
class Billrun_Calculator_Rate_Sms extends Billrun_Calculator_Rate {

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
	protected $legitimateNumberFilters = array('/^0+/', '/[^\d]/');

	public function __construct($options = array()) {
		parent::__construct($options);
		if(isset($options['calculator']['legitimate_number_filters'])) {
			$this->legitimateNumberFilters = $options['calculator']['legitimate_number_filters'];
		}
		$this->loadRates();
	}

	/**
	 * method to get calculator lines
	 */
	protected function getLines() {
		return $this->getQueuedLines(array('type' => array('$in' => array('smpp', 'smsc', 'mmsc'))));
	}

	/**
	 * Write the calculation into DB.
	 * @param $row the line CDR to update. 
	 */
	public function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteRow', array('row' => $row));

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
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteRow', array('row' => $row));
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
		return ($row['type'] == 'smpp' && $row['record_type'] == '1' && $row["cause_of_terminition"] == "100" && in_array($row['called_number'], array('000000000002020', '000000000006060', '000000000007070'))) ||
			($row['type'] == 'smsc' && $row['record_type'] == '1' && $row["cause_of_terminition"] == "100" && preg_match("/^0*9725[82]/",$row["calling_msc"]) ) ||
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
	 * Extract the number from the cdr line.
	 * @param type $row the cdr line
	 * @return type
	 */
	protected function extractNumber($row) {
		$str = ($row['type'] != 'mmsc' ? $row['called_number'] : $row['recipent_addr']);
		foreach ($this->legitimateNumberFilters as $filter) {
			$str = preg_replace($filter, '', $str);
		}
		return $str;
		//return preg_replace('/[^\d]/', '', preg_replace('/^0+/', '', ($row['type'] != 'mmsc' ? $row['called_msc'] : $row['recipent_addr'])));
	}

}
