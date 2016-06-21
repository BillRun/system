<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing rate calculator for Braas
 *
 * @package  calculator
 * @since braas
 */
class Billrun_Calculator_Rate_Usage extends Billrun_Calculator_Rate {

	static protected $usaget;

	public function __construct($options = array()) {
		parent::__construct($options);
		if (isset($options['usaget'])) {
			self::$usaget = $options['usaget'];
			self::$type = $options['type'];
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
	
	protected function getLines() {
		return $this->getQueuedLines(array()); 
	}

	
	protected function isRateLegitimate($rate) {
		return !((is_null($rate) || $rate === false) ||
			(isset($rate['key']) && $rate['key'] == "UNRATED"));
	}
	
	protected function getAddedValues($rate) {
		$added_values = array(
			$this->ratingField => $rate ? $rate->createRef() : $rate,
		);

		if (isset($rate['key'])) {
			$added_values[$this->ratingKeyField] = $rate['key'];
		}

//		if ($rate) {
//			// TODO: push plan to the function to enable market price by plan
//			$added_values[$this->aprField] = Billrun_Calculator_CustomerPricing::getTotalChargeByRate($rate, $row['usaget'], $row['usagev'], $row['plan']);
//		}
		
		return $added_values;
	}
	
	
	public function isLineLegitimate($line) {
		return true;
		}
	
	/**
	 * make the calculation
	 */
	public function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorUpdateRow', array(&$row, $this));
		$current = $row->getRawData();
		$usaget = $row['usaget'];
		$type = $row['type'];
		$rate = $this->getLineRate($row, $usaget, $type);
		if (!$this->isRateLegitimate($rate)) {
			return false;
		}

		// TODO: Create the ref using the collection, not the entity object.
		$rate->collection(Billrun_Factory::db()->ratesCollection());		
		$newData = array_merge($current, $this->getAddedValues($rate));
		$row->setRawData($newData);

		Billrun_Factory::dispatcher()->trigger('afterCalculatorUpdateRow', array(&$row, $this));
		return $row;
	}

	/**
	 * Get the associate rate object for a given CDR line.
	 * @param $row the CDR line to get the for.
	 * @param $usage_type the CDR line  usage type (SMS/Call/etc..)
	 * @return the Rate object that was loaded  from the DB  or false if the line shouldn't be rated.
	 */
	protected function getLineRate($row, $usaget, $type) {
		if ($this->overrideRate || !isset($row[$this->getRatingField()])) {
			//$this->setRowDataForQuery($row);
			$rate = $this->getRateByParams($row,$usaget,$type);
		} else {
			$rate = Billrun_Factory::db()->ratesCollection()->getRef($row[$this->getRatingField()]);
		}
		return $rate;
	}

	/**
	 * Get a matching rate by config params
	 * @return Mongodloid_Entity the matched rate or false if none found
	 */
	protected function getRateByParams($row, $usaget, $type) {
		$query = $this->getRateQuery($row, $usaget, $type);
		Billrun_Factory::dispatcher()->trigger('extendRateParamsQuery', array(&$query, &$row, &$this));
		$rates_coll = Billrun_Factory::db()->ratesCollection();
		$matchedRate = $rates_coll->aggregate($query)->current();

		if ($matchedRate->isEmpty()) {
			return false;
		}

		$key = $matchedRate->get('key');
		return $rates_coll->query(array("key" => $key))->cursor()->current();
	}

	/**
	 * Builds aggregate query from config
	 * 
	 * @return string mongo query
	 */
	protected function getRateQuery($row, $usaget, $type) {
		$match = $this->getBasicMatchRateQuery($row, $usaget);
		$additional = array();
		$group = $this->getBasicGroupRateQuery($row);
		$additionalAfterGroup = array();
		$sort = $this->getBasicSortRateQuery($row);
		$filters = $this->getRateCustomFilters($usaget, $type);
		foreach ($filters as $filter) {
			$handlerClass = Billrun_Calculator_Rate_Filters_Manager::getFilterHandler($filter);
			if (!$handlerClass) {
				Billrun_Factory::log('getRateQuery: cannot find filter hander. Details: ' . print_r($filter, 1));
				continue;
			}
			$handlerClass->updateQuery($match, $additional, $group, $additionalAfterGroup, $sort, $row);
		}
	
		$sortQuery = array();
		if (!empty($sort)) {
			$sortQuery = array(array('$sort' => $sort));
		}
		return array_merge(array(array('$match' => $match)), $additional, array(array('$group' => $group)), $additionalAfterGroup, $sortQuery, array(array('$limit' => 1)));
	}
	
	protected function getBasicMatchRateQuery($row, $usaget) {
		$urt_string = explode(' ', $row['urt']->__toString());
		$sec = $urt_string[1];
		return array_merge(
			Billrun_Util::getDateBoundQuery($sec),
			array('rates.' . $usaget => array('$exists' => true))
		);
	}
	
	protected function getBasicGroupRateQuery($row) {
		return array(
			'_id' => array(
				"_id" => '$_id'
			),
			'key' => array('$first' => '$key')
		);
	}
	
	protected function getBasicSortRateQuery($row) {
		return array();
	}
	
	protected function getRateCustomFilters($usaget, $type) {
		return array_merge(
			Billrun_Factory::config()->getConfigValue($type . '.rate_calculators.' . $usaget, array(), 'array'),
			Billrun_Factory::config()->getConfigValue($type . '.rate_calculators.' . 'BASE', array(), 'array')
		);
	}

}
