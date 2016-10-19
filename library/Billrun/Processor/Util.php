<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Util class for the Processor
 *
 * @package  Util
 * @since    5.2
 */
class Billrun_Processor_Util {

	/**
	 * Gets the datetime from the givven row
	 * 
	 * @param type $row
	 * @param string $dateField
	 * @param string $dateFormat - optional, if not received use PHP default
	 * @param string $timeField - optional, if not received gets time from date field
	 * @param string $timeFormat - optional, if not received use PHP default
	 * @return \DateTime
	 */
	public static function getRowDateTime($row, $dateField, $dateFormat = null, $timeField = null, $timeFormat = null) {
		if (!isset($row[$dateField])) {
			return null;
		}
		$dateValue = $row[$dateField];
		$withTimeField = false;
		if (!empty($timeField) && isset($row[$timeField])) {
			$dateValue .= ' ' . $row[$timeField];
			$withTimeField = true;
		}
		if (!empty($dateFormat)) {
			if (!empty($timeFormat)) {
				$dateFormat .= ' ' . $timeFormat;
			} else if ($withTimeField) {
				return null;
			}
			return DateTime::createFromFormat($dateFormat, $dateValue);
		} else {
			$date = strtotime($dateValue);
			$datetime = new DateTime();
			$datetime->setTimestamp($date);
			return $datetime;
		}
		return null;
	}
}