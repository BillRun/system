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
	protected function getRateQuery($row, $usaget, $type) {
		$sec = $row['urt']->sec;
		$usec = $row['urt']->usec;
		$match = array_merge(
			Billrun_Utils_Mongo::getDateBoundQuery($sec, FALSE, $usec),
			array('key' => $row['rate'])
		);
		$group = $this->getBasicGroupRateQuery($row);
		$sort = $this->getBasicSortRateQuery($row);
	
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
	protected function getAddedValues($rate, $row = array()) {
		$added_values = parent::getAddedValues($rate);
		$added_values['credit'] = $row['credit'];
		$added_values['credit']['usaget'] = current(array_keys($rate['rates'])); // assumes rate is only for one usage type
		return $added_values;
	}
	
}
