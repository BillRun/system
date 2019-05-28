<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing rate calculator for the cloud
 *
 * @package  calculator
 * @since 5.0
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
			// TODO: Rate without a type field is used as a normal rate entity for
			// backward compatability.
			// This should be changed.
			(isset($rate['type']) && $rate['type'] == "service") || 
			(isset($rate['key']) && $rate['key'] == "UNRATED"));
	}
	
	protected function getAddedValues($tariffCategory, $rate, $row = array()) {
		if ($tariffCategory !== 'retail') {
			return array();
		}
		$added_values = array(
			$this->ratingField => $rate ? $rate->createRef() : $rate,
		);

		if (isset($rate['key'])) {
			$added_values[$this->ratingKeyField] = $rate['key'];
		}

//		if ($rate) {
//			// TODO: push plan to the function to enable market price by plan
//			$added_values[$this->aprField] = Billrun_Rates_Utils::getTotalCharge($rate, $row['usaget'], $row['usagev'], $row['plan']);
//		}
		
		return $added_values;
	}
	
	
	public function isLineLegitimate($line) {
		return empty($line['skip_calc']) || !in_array(static::$type, $line['skip_calc']);
	}
	
	/**
	 * gets the data object to save under the line's "rates" attribute
	 * 
	 * @param string $tariffCategory
	 * @param Mongodloid_Entity $rate
	 * @return array
	 */
	protected function getRateData($tariffCategory, $rate) {
		return array(
			'tariff_category' => $tariffCategory,
			'key' => isset($rate['key']) ? $rate['key'] : '',
			'add_to_retail' => isset($rate['add_to_retail']) ? $rate['add_to_retail'] : false,
			'rate' => $rate ? $rate->createRef() : $rate,
		);
		}
	
	/**
	 * make the calculation
	 */
	public function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorUpdateRow', array(&$row, $this));
		$usaget = $row['usaget'];
		$type = $row['type'];
		$customFilters = $this->getRateCustomFilters($type);
		if (empty($customFilters)) {
			Billrun_Factory::log('No custom filters found for type ' . $type . '. Stamp was ' . $row['stamp']);
			Billrun_Factory::dispatcher()->trigger('afterRateNotFound', array(&$row, $this));
			return false;
		}
		// goes over all rate mappings for every tariff categories
		foreach ($customFilters as $tariffCategory => $categoryFilters) {
			$filters = Billrun_Util::getIn($categoryFilters, array($usaget), array());
			if (empty($filters)) {
				Billrun_Factory::log('No custom filters found for type ' . $type . ', usaget ' . $usaget . '. Stamp was ' . $row['stamp']);
				Billrun_Factory::dispatcher()->trigger('afterRateNotFound', array(&$row, $this));
				return false;
			}
			
			$rate = $this->getLineRate($row, $usaget, $type, $tariffCategory, $filters);
			if (!$this->isRateLegitimate($rate)) {
				Billrun_Factory::dispatcher()->trigger('afterRateNotFound', array(&$row, $this));
				return false;
			}

			// TODO: Create the ref using the collection, not the entity object.
			$rate->collection(Billrun_Factory::db()->ratesCollection());		
			$current = $row->getRawData();
			$newData = array_merge($current, $this->getForeignFields(array('rate' => $rate), $current), $this->getAddedValues($tariffCategory, $rate, $row));
			if (!isset($newData['rates'])) {
				$newData['rates'] = array();
			}
			$newData['rates'][] = $this->getRateData($tariffCategory, $rate);
			$row->setRawData($newData);
		}

		Billrun_Factory::dispatcher()->trigger('afterCalculatorUpdateRow', array(&$row, $this));
		return $row;
	}
	
	/**
	 * gets the matching rate for the category from the line  received
	 * 
	 * @param array $row
	 * @param string $tariffCategory
	 * @return rate ref if found, false otherwise
	 */
	protected function getCategoryRate($row, $tariffCategory) {
		if (!isset($row['rates'])) {
			return false;
		}
		foreach ($row['rates'] as $rate) {
			if ($rate['tariff_category'] === $tariffCategory) {
				return $rate['rate'];
			}
		}
		return false;
	}

	/**
	 * Get the associate rate object for a given CDR line.
	 * @param $row the CDR line to get the for.
	 * @param $usage_type the CDR line  usage type (SMS/Call/etc..)
	 * @param $type CDR type
	 * @param $tariffCategory rate category
	 * @param $filters array of filters used to find the rate
	 * @return the Rate object that was loaded  from the DB  or false if the line shouldn't be rated.
	 */
	protected function getLineRate($row, $usaget, $type, $tariffCategory, $filters) {
		if ($this->overrideRate || (!$rate = $this->getCategoryRate($row, $tariffCategory))) {
			//$this->setRowDataForQuery($row);
			$rate = $this->getRateByParams($row,$usaget,$type, $tariffCategory, $filters);
		} else {
			$rate = Billrun_Factory::db()->ratesCollection()->getRef($rate);
		}
		return $rate;
	}

	/**
	 * Get a matching rate by config params
	 * @return Mongodloid_Entity the matched rate or false if none found
	 */
	protected function getRateByParams($row, $usaget, $type, $tariffCategory, $filters) {
		$matchedRate = null;
		foreach ($filters as $currentPriorityFilters) {
			$query = $this->getRateQuery($row, $usaget, $type, $tariffCategory, $currentPriorityFilters);
			if (!$query) {
				continue;
			}
			Billrun_Factory::dispatcher()->trigger('extendRateParamsQuery', array(&$query, &$row, &$this));
			$rates_coll = Billrun_Factory::db()->ratesCollection();
			$matchedRate = $rates_coll->aggregate($query)->current();
			if (!$matchedRate->isEmpty()) {
				break;
			}
		}

		if (empty($matchedRate) || $matchedRate->isEmpty()) {
			return false;
		}

		return $this->findRateByMatchedRate($matchedRate, $rates_coll);
	}

	/**
	 * Builds aggregate query from config
	 * 
	 * @return string mongo query
	 */
	protected function getRateQuery($row, $usaget, $type, $tariffCategory, $filters) {
		$match = $this->getBasicMatchRateQuery($row, $usaget, $tariffCategory);
		$additional = array();
		$group = $this->getBasicGroupRateQuery($row);
		$additionalAfterGroup = array();
		$sort = $this->getBasicSortRateQuery($row);
		if (!$filters) {
			Billrun_Factory::log('No custom filters found for type ' . $type . ', usaget ' . $usaget . '. Stamp was ' . $row['stamp']);
			return FALSE;
		}
		foreach ($filters as $filter) {
			$handlerClass = Billrun_Calculator_Rate_Filters_Manager::getFilterHandler($filter);
			if (!$handlerClass) {
				Billrun_Factory::log('getRateQuery: cannot find filter hander. Details: ' . print_r($filter, 1));
				continue;
			}
			
			$handlerClass->updateQuery($match, $additional, $group, $additionalAfterGroup, $sort, $row);
			if (!$handlerClass->canHandle()) {
				return FALSE;
			}
		}
	
		$sortQuery = array();
		if (!empty($sort)) {
			$sortQuery = array(array('$sort' => $sort));
		}
		return array_merge(array(array('$match' => $match)), $additional, array(array('$group' => $group)), $additionalAfterGroup, $sortQuery, array(array('$limit' => 1)));
	}
	
	protected function getBasicMatchRateQuery($row, $usaget, $tariffCategory) {
		$sec = $row['urt']->sec;
		$usec = $row['urt']->usec;
		$query = array_merge(
			Billrun_Utils_Mongo::getDateBoundQuery($sec, FALSE, $usec),
			array('rates.' . $usaget => array('$exists' => true)),
			array('tariff_category' => $tariffCategory)
		);
        
        if (Billrun_Utils_Plays::isPlaysInUse()) {
            $play = Billrun_Util::getIn($row, 'subscriber.play', '');
            $query['play'] = [
                '$in' => [null, $play],
            ];
        }
        
        return $query;
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
	
	/**
	 * Gets the rate mapping calculators
	 * 
	 * @param string $type
	 * @return array of rate calculators (by priority)
	 */
	protected function getRateCustomFilters($type) {
		return Billrun_Factory::config()->getFileTypeSettings($type, true)['rate_calculators'];
	}
	
	protected function findRateByMatchedRate($rate, $ratesColl) {
		$rawData = $rate->getRawData();
		
 		if (!isset($rawData['key']) || !isset($rawData['_id']['_id']) || !($rawData['_id']['_id'] instanceof MongoId)) {
 			return false;	
 		}
 		$idQuery = array(
 			"key" => $rawData['key'], // this is for sharding purpose
 			"_id" => $rawData['_id']['_id'],
 		);
 		
 		return $ratesColl->query($idQuery)->cursor()->current();
	}

}
