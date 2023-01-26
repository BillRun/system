<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Util class for the plans
 *
 * @package  Util
 * @since    5.1
 */
class Billrun_Plans_Util {

	/**
	 * check if a plan name exists in the system
	 * 
	 * @param type $planName
	 * @return boolean
	 */
	public static function isPlanExists($planName) {
		return $planName === 'BASE' || self::isPlanExistsInDB($planName);
	}

	/**
	 * Check if a plan/service has a price  within given dates (relative to it activation date)
	 */
	public static function hasPriceWithinDates($planOrServiceConfig, $activation, $start, $end) {
		$formatActivation = date(Billrun_Base::base_dateformat, $activation);
		$formatStart = date(Billrun_Base::base_dateformat,  $start);
		$formatEnd = date(Billrun_Base::base_dateformat, $end);

		$startOffset = Billrun_Utils_Time::getMonthsDiff($formatActivation, $formatStart);
		$endOffset = Billrun_Utils_Time::getMonthsDiff($formatActivation, $formatEnd);
		if(isset($planOrServiceConfig['price']))
		foreach($planOrServiceConfig['price'] as $price) {
			if ($price['to'] == 'UNLIMITED') {
				$price['to'] = PHP_INT_MAX;
			}
			if($price['from']  <= $endOffset &&  $startOffset <= $price['to'] ) {
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * check if a given balance_period of a service is within  given date range
	 */
	public static function balancePeriodWithInDates($planOrServiceConfig, $activation, $start, $end) {
		if(empty($planOrServiceConfig['balance_period'])) {
			return TRUE;
		}

		$periodEnd = strtotime($planOrServiceConfig['balance_period'],$activation);

		return $start < $periodEnd && $activation < $end;

	}


	/**
	 * Check if the plan exists in the DB
	 * @param string $planName - Plan name to find
	 * @return boolean True if found
	 */
	protected static function isPlanExistsInDB($planName) {
		$query = Billrun_Utils_Mongo::getDateBoundQuery();
		$query['name'] = $planName;
		$plansCol = Billrun_Factory::db()->plansCollection();
		$count = $plansCol->query($query)->cursor()->count();
		return $count > 0;
	}

}
