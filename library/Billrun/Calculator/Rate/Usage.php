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
	
	/**
	 * make the calculation
	 */
	public function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorUpdateRow', array(&$row, $this));
		$current = $row->getRawData();
		$rate = $this->getLineRate($row);
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
	protected function getLineRate($row) {
		if ($this->overrideRate || !isset($row[$this->getRatingField()])) {
			//$this->setRowDataForQuery($row);
			$rate = $this->getRateByParams($row);
		} else {
			$rate = Billrun_Factory::db()->ratesCollection()->getRef($row[$this->getRatingField()]);
		}
		return $rate;
	}

	/**
	 * Get a matching rate by config params
	 * @return Mongodloid_Entity the matched rate or false if none found
	 */
	protected function getRateByParams($row) {
		$query = $this->getRateQuery($row);
		//$a = print_R(json_encode($query));die;
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
	protected function getRateQuery($row) {
		$match = $this->getBasicMatchRateQuery($row);
		$group = $this->getBasicGroupRateQuery($row);
		$additional = array();
		$sort = $this->getBasicSortRateQuery($row);
		$filters = $this->getRateCustomFilters();
		foreach ($filters as $filter) {
			$handlerClass = Billrun_Calculator_Rate_Filters_Manager::getFilterHandler($filter);
			if (!$handlerClass) {
				Billrun_Factory::log('getRateQuery: cannot find filter hander. Details: ' . print_r($filter, 1));
				continue;
			}
			$handlerClass->updateQuery($match, $group, $additional, $sort, $row);
		}
		
		$queryBegin = array(
			array('$match' => $match),
			array('$group' => $group)
		);
		$queryEnd = array(
			//'$sort' => $sort,
			array('$limit' => 1),
		);
		return array_merge($queryBegin, $additional, $queryEnd);
	}
	
	protected function getBasicMatchRateQuery($row) {
		$sec = $row->urt;
		return array_merge(
			Billrun_Util::getDateBoundQuery($sec),
			array('rates.' . self::$usaget => array('$exists' => true))
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
	
	protected function getRateCustomFilters() {
		return array_merge(
			Billrun_Factory::config()->getConfigValue(self::$type . '.rate_calculators.' . self::$usaget, array(), 'array'),
			Billrun_Factory::config()->getConfigValue(self::$type . '.rate_calculators.' . 'BASE', array(), 'array')
			);
	}

}
