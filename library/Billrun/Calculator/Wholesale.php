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
	protected function getLinePricingData($volumeToPrice, $usageType, $carrier, $zoneKey , $peak) {
		$typedRates =  isset($carrier['zones'][$zoneKey][$usageType][$peak ? 'peak' : 'off_peak']) ?
									$carrier['zones'][$zoneKey][$usageType][$peak ? 'peak' : 'off_peak'] : 
									$carrier['zones'][$zoneKey][$usageType];
		if(!$typedRates['rate'] || !is_array($typedRates['rate'])) {
			Billrun_Factory::log()->log(print_r($carrier,1),Zend_Log::DEBUG);
		}
		$accessPrice = isset($typedRates['access']) ? $typedRates['access'] : 0;

		$price = $accessPrice;
		//Billrun_Factory::log()->log("Rate : ".print_r($typedRates,1),  Zend_Log::DEBUG);
		foreach ($typedRates['rate'] as $key => $currRate) {
			if (0 >= $volumeToPrice) {
				break;
			}//break if no volume left to price.
			$volumeToPriceCurrentRating = ($volumeToPrice - $currRate['to'] < 0) ? $volumeToPrice : $currRate['to']; // get the volume that needed to be priced for the current rating
			$price += floatval((ceil($volumeToPriceCurrentRating / $currRate['interval']) * $currRate['price'])); // actually price the usage volume by the current 
			$volumeToPrice = $volumeToPrice - $volumeToPriceCurrentRating; //decressed the volume that was priced
		}
		$ret[$this->pricingField] = $price;
		return $ret;
	}
	
	/**
	 * 
	 * TODO remove
	 * @see Billrun_Calculator_Rate::getLineRate
	 *
	 *
	protected function getLineZone($row, $usage_type) {

		$called_number = $row->get('called_number');
		$ocg = $row->get('out_circuit_group');
		$line_time = $row->get('unified_record_time');

		$rates = Billrun_Factory::db()->ratesCollection();
		if( $this->isLineIncoming($row) ) {
			$zoneKey = 'incoming';
		} else {
			$zoneKey= false;
		}
		
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
			$zoneKey = reset($matched_rates)['key'];
		}

		return $zoneKey;
	}
	*/
		
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
	
	/**
	 * Check if the cdr line  is incoming line  or outgoing
	 * @param type $row the line to check
	 * @return boolean true if the line  is incoming  false otherwise
	 */
	protected function isLineIncoming($row) {
		$ocg = $row->get('out_circuit_group');
		return $ocg == 0 || $ocg == 3060 || $ocg == 3061 ;
	}
}

