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
class Billrun_Calculator_Wholesale_Sms extends Billrun_Calculator_Wholesale {

	const DEF_CALC_DB_FIELD = 'provider_zone';
	
	protected $ratingField = self::DEF_CALC_DB_FIELD;	
	/**
	 * method to get calculator lines
	 */
	protected function getLines() {
		$lines = Billrun_Factory::db()->linesCollection();

		return $lines->query()
				->in('type', array('nsn'))				
				->equals('usaget','sms')
				->notExists($this->ratingField)->cursor()->limit($this->limit);
	}

	/**
	 * Write the calculation into DB
	 */
	protected function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteRow', array('row' => $row));

		$rate = $this->getLineZone($row, $row['usaget']);

		$current = $row->getRawData();
		
		$added_values = array(			
			$this->ratingField => $rate ? $rate->createRef() : $rate,
		);
		
		$newData = array_merge($current, $added_values);
		$row->setRawData($newData);

		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteRow', array('row' => $row));
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineRate
	 */
	protected function getLineZone($row, $usage_type) {
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

		return new Mongodloid_Entity(reset($matched_rates),$rates);
	}
	
	/**
	 * Get an array of prefixes for a given number.
	 * @param type $str the number to get  prefixes to.
	 * @return Array the possible prefixes of the number.
	 */
	protected function getPrefixes($str) {
		$prefixes = array();
		for ($i = 0; $i < strlen($str); $i++) {
			$prefixes[] = substr($str, 0, $i + 1);
		}
		return $prefixes;
	}
}
