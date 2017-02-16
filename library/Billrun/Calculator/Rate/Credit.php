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
		$match = array_merge(
			Billrun_Utils_Mongo::getDateBoundQuery($sec),
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
	
}
