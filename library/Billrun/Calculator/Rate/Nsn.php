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

	/**
	 * Write the calculation into DB
	 */
	protected function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteRow', array('row' => $row));
		$usage_type = $this->getLineUsageType($row);
		$volume = $this->getLineVolume($row, $usage_type);
		$rate = $this->getLineRate($row, $usage_type);
		
		$current = $row->getRawData();

		$added_values = array(
			'usaget' => $usage_type,
			'usagev' => $volume,
			$this->ratingField => $rate? $rate->createRef() : $rate,
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
		if($usage_type == 'call' ) {
				return  $row['duration'] ;
		}
		if($usage_type == 'sms' ) {
			return 1;
		}
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineUsageType
	 */	
	protected function getLineUsageType($row) {
		switch ($row['record_type']) {
			case '08':
			case '09':
				return 'sms';
				break;
				
			case '11':
			case '12':
			case '01':
			case '02':				
			default:				
				return 'call';
				break;

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
		$line_time = $row->get('unified_record_time');

		$rates = Billrun_Factory::db()->ratesCollection();
		$rate = FALSE;

		if ($record_type == "01" || //MOC call
			($record_type == "11" && ($icg == "1001" || $icg == "1006" || ($icg >= "1201" && $icg <= "1209")) && ($ocg != '3051' && $ocg != '3050'))// Roaming on Cellcom and the call is not to a voice mail
		) {
			$called_number_prefixes = $this->getPrefixes($called_number);

			$base_match = array(
				'$match' => array(
					'params.prefix' => array(
						'$in' => $called_number_prefixes,
					),
					'rates.' . $usage_type => array('$exists' => true),
					'params.out_circuit_group' => array(
						'$elemMatch' => array(
							'from' => array(
								'$lte' => $ocg,
							),
							'to' => array(
								'$gte' => $ocg
							)
						)
					),
					'from' => array(
						'$lte' => $line_time,
					),
					'to' => array(
						'$gte' => $line_time,
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

			if (empty($matched_rates)) {
				$base_match = array(
					'$match' => array(
						'key' => 'UNRATED',
					),
				);
				$matched_rates = $rates->aggregate($base_match);
			}

			$rate = new Mongodloid_Entity(reset($matched_rates),$rates);
		}
		return $rate;
	}

	
}
