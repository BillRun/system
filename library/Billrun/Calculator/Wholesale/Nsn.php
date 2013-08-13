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
class Billrun_Calculator_Wholesale_Nsn extends Billrun_Calculator_Wholesale {

	const MAIN_DB_FIELD = 'provider_zone';
	
	protected $ratingField = self::MAIN_DB_FIELD;	
			
		
	/**
	 * method to get calculator lines
	 */
	protected function getLines() {
		$lines =  $this->getQueuedLines(array('type'=> 'nsn'));		
		return $lines;
	}
	
	/**
	 * Write the calculation into DB
	 */
	protected function updateRow($row) {

		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteRow', array('row' => $row));

		$rate = $this->getLineZone($row, $row['usaget']);

		$current = $row->getRawData();
		
		$added_values = array(			
			$this->ratingField => $rate instanceof Mongodloid_Entity ? $rate->createRef() : $rate,
		);
		
		$newData = array_merge($current, $added_values);
		$row->setRawData($newData);

		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteRow', array('row' => $row));
		return true;
	}

	/**
	 * TODO remove
	 * @see Billrun_Calculator_Rate::getLineRate
	 *
	 */
	protected function getLineZone($row, $usage_type) {
		//TODO  change this  to be configurable.
		$called_number =  ($usage_type == 'call') ? $row->get('called_number') :  preg_replace('/[^\d]/', '', preg_replace('/^0+/', '', ( $row['called_number'])));
		
		$line_time = $row->get('unified_record_time');

		$rates = Billrun_Factory::db()->ratesCollection();
		
		$zoneKey= false;		

		$called_number_prefixes = $this->getPrefixes($called_number);

		$base_match = array(
			'$match' => array(
				'params.prefix' => array(
					'$in' => $called_number_prefixes,
				),
				'rates.' . $usage_type => array('$exists' => true),				
				'from' => array(
					'$lte' => $line_time,
				),
				'to' => array(
					'$gte' => $line_time,
				),
			)
		);
		
		if($usage_type == 'call') {
			$carrier_cg = $row->get('out_circuit_group');
			$base_match['$match']['params.out_circuit_group'] = array(
					'$elemMatch' => array(
						'from' => array(
							'$lte' => $carrier_cg,
						),
						'to' => array(
							'$gte' => $carrier_cg
						)
					)
				);
		}

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
		if (!empty($matched_rates)) {
			$zoneKey =new Mongodloid_Entity(reset($matched_rates),$rates);
		}

		return $zoneKey;
	}

	/**
	 * get all the prefixes from a given number
	 * @param type $str
	 * @return type
	 */
	protected function getPrefixes($str) {
		//TODO  change this  to be configurable.
		$str = preg_replace("/^01\d/", "", $str );
		$prefixes = array();
		for ($i = 0; $i < strlen($str); $i++) {
			$prefixes[] = substr($str, 0, $i + 1);
		}
		return $prefixes;
	}

	protected function isLineLegitimate($line) {
		return	in_array($line['usaget'],array('call','sms')) &&
				in_array($line['record_type'], $this->wholesaleRecords );
	}
	
}
