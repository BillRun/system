<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator class for credit records
 *
 * @package  calculator
 * @since    0.5
 */
class Billrun_Calculator_Rate_Credit extends Billrun_Calculator_Rate_Usage {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = "credit";

	/**
	 * see Billrun_Calculator_Rate_Usage::getRateQuery
	 * 
	 * @return string mongo query
	 */
	protected function getRateQuery($row, $usaget, $type, $tariffCategory, $filters) {
		$sec = $row['urt']->sec;
		$usec = $row['urt']->usec;
		$match = array_merge(
				Billrun_Utils_Mongo::getDateBoundQuery($sec, FALSE, $usec), array('key' => $row['rate'])
		);
		$group = $this->getBasicGroupQuery($row);
		$sort = $this->getBasicSortQuery($row);

		$sortQuery = array();
		if (!empty($sort)) {
			$sortQuery = array(array('$sort' => $sort));
		}
		return array_merge(array(array('$match' => $match)), array(array('$group' => $group)), $sortQuery, array(array('$limit' => 1)));
	}

	/**
	 * see Billrun_Calculator_Rate_Usage::getAddedValues
	 * 
	 * @return array values to add from rate
	 */
	protected function getAddedValues($tariffCategory, $rate, $row = array()) {
		$added_values = parent::getAddedValues($tariffCategory, $rate, $row);
		$added_values['credit'] = $row['credit'];
		$added_values['credit']['usaget'] = current(array_keys($rate['rates'])); // assumes rate is only for one usage type
		return $added_values;
	}

	public function updateRow($row) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorUpdateRow', array(&$row, $this));
		$usaget = $row['usaget'];
		$type = $row['type'];
		$rate = $this->getLineRate($row, $usaget, $type, 'retail', array());
		if (!$this->isRateLegitimate($rate)) {
			return false;
		}
		$rate->collection(Billrun_Factory::db()->ratesCollection());
		$current = $row->getRawData();
		$newData = array_merge($current, $this->getForeignFields(array('rate' => $rate), $current), $this->getAddedValues('retail', $rate, $row));
		if (!isset($newData['rates'])) {
			$newData['rates'] = array();
		}
		$newData['rates'][] = $this->getRateData('retail', $rate);
		$row->setRawData($newData);

		Billrun_Factory::dispatcher()->trigger('afterCalculatorUpdateRow', array(&$row, $this));
		return $row;
	}

	/**
	 * Get a matching rate by config params
	 * @return Mongodloid_Entity the matched rate or false if none found
	 */
	protected function getRateByParams($row, $usaget, $type, $tariffCategory, $filters) {
		$query = $this->getRateQuery($row, $usaget, $type, 'retail', array());
		Billrun_Factory::dispatcher()->trigger('extendRateParamsQuery', array(&$query, &$row, &$this));
		$rates_coll = Billrun_Factory::db()->ratesCollection();
		$matchedRate = $rates_coll->aggregate($query)->current();
		if (empty($matchedRate) || $matchedRate->isEmpty()) {
			return false;
		}

		return $this->getFullEntityData($matchedRate);
	}

}
