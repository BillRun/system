<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator class for SMS records received in realltime
 *
 * @package  calculator
 * @since 4.0
 */
class Billrun_Calculator_Rate_Callrt extends Billrun_Calculator_Rate {

	static protected $type = 'callrt';
	
	public function __construct($options = array()) {
		parent::__construct($options);
		$this->loadRates();
	}

	/**
	 * Check if a given line should be rated.
	 * @param type $row
	 * @return type
	 */
	protected function shouldLineBeRated($row) {
		return true;
	}

	/**
	 * 
	 * @deprecated since version 2.9
	 */
	protected function getLineUsageType($row) {	
	}

	/**
	 * 
	 * @deprecated since version 2.9
	 */
	protected function getLineVolume($row) {
	}
	
	/**
	 * @see Billrun_Calculator_Rate::getLineRate
	 */
	protected function getLineRate($row) {
		$called_number = $row->get('calling_number');
		$line_time = $row->get('urt');
		$usage_type = $row->get('usaget');
		$matchedRate = $this->getRateByParams($called_number, $usage_type, $line_time);

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
		//$rates_coll = Billrun_Factory::db()->ratesCollection();
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
}
