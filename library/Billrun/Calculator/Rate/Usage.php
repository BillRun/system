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

	use Billrun_Traits_EntityGetter {
		getFullEntityDataQuery as entityGetterGetFullEntityDataQuery;
		getBasicGroupQuery as entityGetterGetBasicGroupQuery;
		getBasicMatchQuery as entityGetterGetBasicMatchQuery;
	}

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
		$params = [
			'type' => $type,
			'usaget' => $usaget,
		];

		$rates = $this->getMatchingEntitiesByCategories($row, $params);
		if (empty($rates)) {
			Billrun_Factory::dispatcher()->trigger('afterRateNotFound', array(&$row, $this));
			return false;
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
			$rate = $this->getRateByParams($row, $usaget, $type, $tariffCategory, $filters);
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
		$params = [
			'type' => $type,
			'usaget' => $usaget,
		];

		return $this->getEntityByFilters($row, $filters, $tariffCategory, $params);
	}

	//------------------- Entity Getter functions ----------------------------------------------------

	protected function getCollection($params = []) {
		return Billrun_Factory::db()->ratesCollection();
	}

	protected function getFilters($row = [], $params = []) {
		$type = $params['type'] ?: '';
		return Billrun_Factory::config()->getFileTypeSettings($type, true)['rate_calculators'];
	}

	protected function getBasicMatchQuery($row, $category = '', $params = []) {
		$usaget = $params['usaget'];

		$query = array_merge(
				$this->entityGetterGetBasicMatchQuery($row, $category, $params),
				['rates.' . $usaget => ['$exists' => true]],
				['tariff_category' => $category]
		);
		if (Billrun_Utils_Plays::isPlaysInUse()) {
			$play = Billrun_Util::getIn($row, 'subscriber.play', Billrun_Util::getIn(Billrun_Utils_Plays::getDefaultPlay(), 'name', ''));
			$query['play'] = [
				'$in' => [null, $play],
			];
		}

		return $query;
	}

	protected function getBasicGroupQuery($row, $category = '', $params = []) {
		$query = $this->entityGetterGetBasicGroupQuery($row, $category, $params);
		$query['key'] = [
			'$first' => '$key',
		];

		return $query;
	}

	protected function getCategoryFilters($categoryFilters, $row = [], $params = []) {
		$usaget = $params['usaget'] ?: '';
		return Billrun_Util::getIn($categoryFilters, [$usaget, 'priorities'], Billrun_Util::getIn($categoryFilters, $usaget, []));
	}

	protected function getConditionEntityKey($params = []) {
		return 'rate_key';
	}

	protected function afterEntityFound(&$row, $entity, $category = '', $params = []) {
		// TODO: Create the ref using the collection, not the entity object.
		$entity->collection(Billrun_Factory::db()->ratesCollection());
		$current = $row->getRawData();
		$newData = array_merge(
				$current,
				$this->getForeignFields(['rate' => $entity], $current),
				$this->getAddedValues($category, $entity, $row)
		);

		if (!isset($newData['rates'])) {
			$newData['rates'] = [];
		}

		$newData['rates'][] = $this->getRateData($category, $entity);

		if (isset($entity['rounding_rules'])) {
			$newData['rounding_rules'] = $entity['rounding_rules'];
		}
		$row->setRawData($newData);
	}

	public function getFullEntityDataQuery($rawEntity) {
		$query = $this->entityGetterGetFullEntityDataQuery($rawEntity);
		if (!$query || !isset($rawEntity['key'])) {
			return false;
		}

		$query['key'] = $rawEntity['key']; // this is for sharding purpose
		return $query;
	}

	//------------------- Entity Getter functions - END ----------------------------------------------
}
