<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Util class for the mongo
 *
 * @package  Util
 * @since    4.5
 */
class Billrun_Utils_Mongo {
	/**
	 * Get a mongo date object based on a period object.
	 * @param period $period
	 * @return \MongoDate or false on failure
	 * @todo Create a period object.
	 */
	public static function getDateFromPeriod($period) {
		if(!$period) {
			Billrun_Factory::log("Invalid data!", Zend_Log::ERR);
			return null;
		}
		
		if ($period instanceof MongoDate) {
			return $period;
		}
		if (isset($period['sec'])) {
			return new MongoDate($period['sec']);
		}

		$duration = $period['duration'];
		// If this plan is unlimited.
		// TODO: Move this logic to a more generic location
		if ($duration == "UNLIMITED") {
			return new MongoDate(strtotime(self::UNLIMITED_DATE));
		}
		if (isset($period['units'])) {
			$unit = $period['units'];
		} else if (isset($period['unit'])) {
			$unit = $period['unit'];
		} else {
			$unit = 'months';
		}
		return new MongoDate(strtotime("tomorrow", strtotime("+ " . $duration . " " . $unit)) - 1);
	}
}
