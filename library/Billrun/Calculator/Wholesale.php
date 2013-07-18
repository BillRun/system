<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Wholesale
 *
 * @author eran
 */
abstract class Billrun_Calculator_Wholesale extends Billrun_Calculator {

	
	/**
	 * Get pricing data for a given rate / subcriber.
	 * @param type $volume The usage volume (seconds of call, count of SMS, bytes  of data)
	 * @param type $usageType The type  of the usage (call/sms/data)
	 * @param type $rate The rate of associated with the usage.
	 * @param type $subr the  subscriber that generated the usage.
	 * @return type
	 */
	protected function getLinePricingData($volumeToPrice, $usageType, $carrier, $zoneKey) {
		$typedRates = $carrier['zones'][$zoneKey][$usageType];
		$accessPrice = isset($typedRates['access']) ? $typedRates['access'] : 0;

		$interval = $typedRates['rate']['interval'] ? $typedRates['rate']['interval'] : 1;
		$ret[$this->pricingField] = $accessPrice + ( floatval((ceil($volumeToPrice / $interval) ) * $typedRates['rate']['price']) );

		return $ret;
	}
	
	/**
	 * @see Billrun_Calculator_Rate::getLineRate
	 */
	protected function getLineZone($row, $usage_type) {

		$called_number = $row->get('called_number');
		$ocg = $row->get('out_circuit_group');
		$line_time = $row->get('unified_record_time');

		$rates = Billrun_Factory::db()->ratesCollection();
		$zoneKey = 'none';
		
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
			$zoneKey = reset($matched_rates)['key'];
		}

		return $zoneKey;
	}

	protected function getLineCarrier($row) {

		$called_number = $row->get('called_number');
		$ocg = $row->get('out_circuit_group');
		$icg = $row->get('in_circuit_group');
		$line_time = $row->get('unified_record_time');

		$rates = Billrun_Factory::db()->carriersCollection();
		$carrier = FALSE;
		
		$called_number_prefixes = $this->getPrefixes($called_number);

		$query = array(			
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
		);


		$matchedCarrier = $rates->query($query)->cursor()->current();
		if ($matchedCarrier->isValid()) {
			$carrier = $matchedCarrier;
		}

		return $carrier;
	}
	
}

