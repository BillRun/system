<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
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
	protected $usaget = 'call';

	public function __construct($options = array()) {
		parent::__construct($options);
		if (isset($options['usaget'])) {
			$this->usaget = $options['usaget'];
		}
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
	 * get rate by params, first try to find in cache (because of real-time)
	 * @param type $row
	 * @return Mongodloid_Entity the matched rate or false if none found
	 */
	protected function getRateByParams($row) {
		// we can use cache because we are on real-time (current time rate query)
		$cacheKey = $this->rowDataForQuery['country_code'] . 'XYZ' . $this->rowDataForQuery['called_number']; // XYZ used to distinguish cases when the concatenation can be repeated by mistake
		$cachePrefix = 'billrun_rate_callrt';
		$cache = Billrun_Factory::cache();
		if ($cache) {
			$cachedRate = $cache->get($cacheKey, $cachePrefix);
			if (!is_null($cachedRate) && $cachedRate !== FALSE) {
				return new Mongodloid_Entity($cachedRate);
			}
		} else {
			return parent::getRateByParams($row);
		}
		$rate = parent::getRateByParams($row);
		$rateRawData = $rate->getRawData();
		$cache->set($cacheKey, $rateRawData, $cachePrefix, strtotime('tomorrow') - 1);
		return $rate;
	}

	/**
	 * method to identify the destination of the call
	 * 
	 * @param array $row billing line
	 * 
	 * @return string
	 */
	protected function get_called_number($row) {
		$called_number = $row->get('called_number');
		if (empty($called_number)) {
			$called_number = $row->get('dialed_digits');
			if (empty($called_number)) {
				$called_number = $row->get('connected_number');
			}
		}
		return $called_number;
	}

	/**
	 * Assistance function to generate 'prefix' field query with current row.
	 * 
	 * @return array query for 'prefix' field
	 */
	protected function getPrefixMatchQuery() {
		return array('$in' => Billrun_Util::getPrefixes($this->getCleanNumber($this->rowDataForQuery['called_number'])));
	}

	protected function getAggregateId() {
		return array(
			"_id" => '$_id',
			"pref" => '$params.prefix',
			"msc" => '$params.msc'
		);
	}

	protected function getRatesExistsQuery($row, $key) {
		$keyUsaget = str_replace('rates.', '', $key);
		if ($this->usaget === $keyUsaget) {
			return array(
				'$exists' => true,
				'$ne' => array(),
			);
		}
		return null;
	}

}
