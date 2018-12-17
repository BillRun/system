<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
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
		$this->loadTadigs();
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineVolume
	 */
	protected function getLineVolume($row, $usage_type) {
		if (in_array($usage_type, array('call', 'incoming_call'))) {
			if (isset($row['duration'])) {
				return $row['duration'];
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
				return 'sms';
			case '09':
				return 'incoming_sms';

			case '02':
			case '12':
				return 'incoming_call';
				
			case '31':
				if(preg_match('/^RCEL/',$row['out_circuit_group_name'])) {
					return 'incoming_call';
				} else {
					return 'call';
				}
				
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
		$roamingRate = $this->getRoamingLineRate($row, $usage_type);
		if ($roamingRate) {
			return $roamingRate;
		}
		$record_type = $row->get('record_type');
		$called_number = $row->get('called_number');
		$ocg = $row->get('out_circuit_group');
		$icg = $row->get('in_circuit_group');
		$line_time = $row->get('urt');
		$matchedRate = false;
                
		if ($record_type == "01" || //MOC call
				(in_array($record_type, array("11","30")) && in_array($icg, Billrun_Util::getRoamingCircuitGroups()) &&
			$ocg != '3060' && $ocg != '3061') // Roaming on Cellcom and not redirection
		) {
			$matchedRate = $this->getRateByParams($called_number, $usage_type, $line_time, $ocg);
		} else if ($record_type == '30' && isset($row['ild_prefix'])) {
			$called_number = preg_replace('/^016/', '', $called_number);
			$matchedRate = $this->getRateByParams($called_number, $usage_type, $line_time, $ocg);
		} else if ($record_type == "31" //STC call
			&& in_array($icg, Billrun_Util::getRoamingCircuitGroups()) && $usage_type != 'incoming_call' &&
			$ocg != '3060' && $ocg != '3061' // Roaming on Cellcom and not redirection
		) { 
			$matchedRate = $this->getRateByParams($called_number, $usage_type, $line_time, $ocg);
		}

		return $matchedRate;
	}

	/**
	 * Get a matching rate by the supplied params
	 * @param string $called_number the number called
	 * @param string $usage_type the usage type (call / sms ...)
	 * @param MongoDate $urt the time of the event
	 * @param string $ocg the out circuit group of the event. If not supplied, ocg will be ignored in the search.
	 * @return Mongodloid_Entity the matched rate or UNRATED rate if none found
	 */
	protected function getRateByParams($called_number, $usage_type, $urt, $ocg = null) {
		$matchedRate = $this->rates['UNRATED'];
		$called_number_prefixes = Billrun_Util::getPrefixes($called_number);
		foreach ($called_number_prefixes as $prefix) {
			if (isset($this->rates[$prefix])) {
				foreach ($this->rates[$prefix] as $rate) {
					if (isset($rate['rates'][$usage_type]) && (!isset($rate['params']['fullEqual']) || $prefix == $called_number)) {
						if ($rate['from'] <= $urt && $rate['to'] >= $urt) {
							if (is_null($ocg)) {
								$matchedRate = $rate;
								break 2;
							} else {
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
		}
		return $matchedRate;
	}
	//todo: move the regex and rate keys to config
	protected function getLineAdditionalValues($row) {
		$circuit_groups = Billrun_Factory::config()->getConfigValue('Rate_Nsn.calculator.whloesale_incoming_rate_key');
		$rate_key = null;
		if( in_array($row['record_type'],array('30','11')) &&  $this->valueWithinRanges($row['in_circuit_group'], $circuit_groups['icg']) ) {
			if(preg_match('/^(997|972)?1800/',$row['called_number'])) {
				$rate_key = 'IL_TF';
			} else if(preg_match('/^(997|972)?1700/',$row['called_number'])) {
				$rate_key = 'IL_1700';
			}
		}
		if(	in_array($row['record_type'],array('12','02','30','31')) &&
					 preg_match('/(^RCEL)|(^$)/',$row['out_circuit_group_name']) && ( (preg_match('/^(972)?5/',$row['called_number'])) || !$rate_key) ) {
			$rate_key = 'IL_MOBILE';
		}
		$additional_properties = $this->getAdditionalProperties();
		if(isset($rate_key)){
			return array($additional_properties['wholesale_rate_key'] => $rate_key);
		}
		return array();
	}
		
	protected function getAdditionalProperties() {
		return Billrun_Factory::config()->getConfigValue('Rate_Nsn.calculator.additional_properties');
	}
	
	
	/**
	 * Verify  the  a given value  is within given ranges
	 * @param type $value the  value to check
	 * @param type $ranges the ranges to compare to  structure  as follow 
	 *			array(array('min' => 0 , 'max' => 100)) matches values 0-100
	 * @return boolean TRUE is the  value is within the ranges FALSE otherwise.
	 */
	protected function valueWithinRanges($value,$ranges) {
			foreach($ranges as $range) {
				if( $range['min'] >= $value && $value <= $range['max'] ) {
					return TRUE;
				}
			}
			return FALSE;
	}
	
	protected function getRoamingRateQuery($row, $usage_type) {
		$query = parent::getRoamingRateQuery($row, $usage_type);
		if (!$query) {
			return false;
		}
		$alpha3 = $this->getAlpha3($row, $usage_type);
		if (!$alpha3) {
			return false;
		}
		$query['params.roaming_alpha3'] = array(
			'$in' => array($alpha3, null), // null is for default rate where alpha3 field does not exist
		);

		return $query;
	}
	
	protected function getRoamingRateSort($row, $usage_type) {
		return array(
			'params.roaming_alpha3' => -1,
		);
	}
	
	protected function getAlpha3($row, $usage_type) {
		$prefixes = Billrun_Util::getPrefixes($row['called_number']);
		if (in_array('972', $prefixes)) {
			return 'ISR';
		}
		$match = array(
			'from' => array(
				'$lte' => new MongoDate($row['urt']->sec),
			),
			'to' => array(
				'$gte' => new MongoDate($row['urt']->sec),
			),
			'alpha3' => array(
				'$exists' => true,
			),
			'kt_prefixes' => array(
				'$in' => $prefixes,
			),
		);
		$unwind = '$kt_prefixes';
		$group = array(
			'_id' => array(
				'_id' => '$_id',
				'pref' => '$kt_prefixes',
			),
			'kt_prefixes' => array(
				'$first' => '$kt_prefixes',
			),
			'key' => array(
				'$first' => '$key',
			),
			'alpha3' => array(
				'$first' => '$alpha3',
			),
		);
		$match2 = array(
			'kt_prefixes' => array(
				'$in' => $prefixes,
			),
		);
		$sort = array(
			'kt_prefixes' => -1,
		);
		$aggregateQuery = array(
			array('$match' => $match),
			array('$unwind' => $unwind),
			array('$group' => $group),
			array('$match' => $match2),
			array('$sort' => $sort),
			array('$limit' => 1),
		);
		$rates_coll = Billrun_Factory::db()->ratesCollection();
		$rate = $rates_coll->aggregate($aggregateQuery);
		if (empty($rate) || !isset($rate[0]['alpha3'])) {
			return '';
		}
		return $rate[0]['alpha3'];
	}
		
}
