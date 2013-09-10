<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing calculator class for SMS records
 *
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
	 * method to get calculator lines
	 */
	protected function getLines() {
		return $this->getQueuedLines(array('type' => array('$in' => array('smpp', 'smsc', 'mmsc'))));
	}

	/**
	 * Write the calculation into DB.
	 * @param $row the line CDR to update. 
	 */
	protected function updateRow($row) {
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
	 * Identify if the row belong to calculator
	 * 
	 * @return boolean true if the row identify as belonging to the calculator, else false
	 */
	protected function identify($row) {
		return true;
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineRate
	 */
	protected function getLineRate($row, $usage_type) {
		if (($row['type'] == 'smpp' && $row['record_type'] == '1' && $row["cause_of_terminition"] == "100" && in_array($row['calling_number'], array('000000000002020', '000000000006060', '000000000007070'))) ||
			($row['type'] == 'smsc' && $row['record_type'] == '1' && $row["cause_of_terminition"] == "100" && $row["calling_msc"] != "000000000000000" ) ||
			($row['type'] == 'mmsc' && in_array('S', $row['action']) && $row['final_state'] == 'S' && preg_match('^\+\d+\/TYPE\s*=\s*.*golantelecom', $row['mm_source_addr']))
		) {
			$called_number = preg_replace('/[^\d]/', '', preg_replace('/^0+/', '', ($row['type'] != 'mmsc' ? $row['called_msc'] : $row['recipent_addr'])));
			$line_time = $row['unified_record_time'];

			$rates = Billrun_Factory::db()->ratesCollection();
			//Billrun_Factory::log()->log("row : ".print_r($row ,1),  Zend_Log::DEBUG);
//		$type = $row['type'] == 'mmsc' ? 'mms' : 'sms';
			$called_number_prefixes = $this->getPrefixes($called_number);
			//Billrun_Factory::log()->log("prefixes  for $called_number : ".print_r($called_number_prefixes ,1),  Zend_Log::DEBUG);
			$base_match = array(
				'$match' => array(
					'params.prefix' => array(
						'$in' => $called_number_prefixes,
					),
					"rates.$usage_type" => array(
						'$exists' => true
					),
					"from" => array(
						'$lte' => $line_time
					),
					"to" => array(
						'$gte' => $line_time
					),
				)
			);

			$unwind = array(
				'$unwind' => '$params.prefix',
			);

			$sort = array(
				'$sort' => array(
					'params.prefix' => -1,
				),
			);

			$match2 = array(
				'$match' => array(
					'params.prefix' => array(
						'$in' => $called_number_prefixes,
					),
				)
			);

			$matched_rates = $rates->aggregate($base_match, $unwind, $sort, $match2);
			//Billrun_Factory::log()->log("rates : ".print_r($matched_rates ,1),  Zend_Log::DEBUG);
			if (empty($matched_rates)) {
				return FALSE;
			}

			return new Mongodloid_Entity(reset($matched_rates), $rates);
		} else {
			return false;
		}
	}
	
	/**
	 * @see Billrun_Calculator::isLineLegitimate
	 */
	protected function isLineLegitimate($line) {
		return $line['type'] == 'smsc' || $line['type'] == 'mmsc' || $line['type'] == 'smpp';
	}
}
