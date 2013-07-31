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
class Billrun_Calculator_Wholesale_Call extends Billrun_Calculator_Wholesale {

	const DEF_CALC_DB_FIELD = 'provider_zone';
	
	protected $ratingField = self::DEF_CALC_DB_FIELD;	
		
	/**
	 * method to get calculator lines
	 */
	protected function getLines() {
		$lines = Billrun_Factory::db()->linesCollection();

		return $lines->query()
				->in('type', array('nsn'))
				->in('record_type', array('11','12'))
				->equals('usaget','call')
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
			$this->ratingField => $rate instanceof Mongodloid_Entity ? $rate->createRef() : $rate,
		);
		
		$newData = array_merge($current, $added_values);
		$row->setRawData($newData);

		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteRow', array('row' => $row));
	}

	/**
	 * TODO remove
	 * @see Billrun_Calculator_Rate::getLineRate
	 *
	 */
	protected function getLineZone($row, $usage_type) {

		$called_number =  $this->isLineIncoming($row) ? $row->get('calling_number') :  $row->get('called_number') ;
		$ocg = $this->isLineIncoming($row) ? $row->get('in_circuit_group') :  $row->get('out_circuit_group');
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
		$prefixes = array();
		for ($i = 0; $i < strlen($str); $i++) {
			$prefixes[] = substr($str, 0, $i + 1);
		}
		return $prefixes;
	}
	
}
