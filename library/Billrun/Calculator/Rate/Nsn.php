<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing calculator class for nsn records
 *
 * @package  calculator
 * @since    0.5
 */
class Billrun_Calculator_Rate_Nsn extends Billrun_Calculator_Rate {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = "nsn";

	public function __construct($options = array()) {
		parent::__construct($options);
		$this->loadRates();
	}

	/**
	 * Write the calculation into DB
	 */
	public function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteRow', array($row, $this));
		$usage_type = $this->getLineUsageType($row);
		$volume = $this->getLineVolume($row, $usage_type);
		$rate = $this->getLineRate($row, $usage_type);
		if (isset($rate['key']) && $rate['key'] == "UNRATED") {
			return false;
		}
		$current = $row->getRawData();

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
		if (in_array($usage_type, array('call', 'incoming_call'))) {
			if (isset($row['duration'])) {
				return $row['duration'];
			} else if ($row['record_type'] == '31') { // terminated call
				return 0;
			}
		}
		if ($usage_type == 'sms') {
			return 1;
		}
		return null;
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineUsageType
	 */
	protected function getLineUsageType($row) {
		switch ($row['record_type']) {
			case '08':
			case '09':
				return 'sms';
			case '02':
			case '12':
				return 'incoming_call';
			case '11':
			case '01':
			case '30':
			default:
				return 'call';
		}
		return 'call';
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineRate
	 */
	protected function getLineRate($row, $usage_type) {
		$record_type = $row->get('record_type');

		$called_number = $row->get('called_number');
		$ocg = $row->get('out_circuit_group');
		$icg = $row->get('in_circuit_group');
		$line_time = $row->get('urt');
		$matchedRate = false;

		if ($record_type == "01" || //MOC call
				($record_type == "11" && ($icg == "1001" || $icg == "1006" || ($icg >= "1201" && $icg <= "1209")) &&
				$ocg != '3060' && $ocg != '3061')) { // Roaming on Cellcom and not redirection
			$called_number_prefixes = $this->getPrefixes($called_number);
			foreach ($called_number_prefixes as $prefix) {
				if (isset($this->rates[$prefix])) {
					foreach ($this->rates[$prefix] as $rate) {
						if (isset($rate['rates'][$usage_type])) {
							if ($rate['from'] <= $line_time && $rate['to'] >= $line_time) {
								foreach ($rate['params']['out_circuit_group'] as $groups) {
									if ($groups['from'] <= $ocg && $groups['to'] >= $ocg) {
										$matchedRate = $rate;
										break 3;
									}
								}
							}
						}
					}
				}
			}
			if (!$matchedRate) {
				$matchedRate = $this->rates['UNRATED'];
			}
		}

		return $matchedRate;
	}

}
