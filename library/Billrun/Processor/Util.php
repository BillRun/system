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
	 * Gets the datetime from the given row
	 * 
	 * @param type $userFields
	 * @param string $dateField
	 * @param string $dateFormat - optional, if not received use PHP default
	 * @param string $timeField - optional, if not received gets time from date field
	 * @param string $timeFormat - optional, if not received use PHP default
	 * @return \DateTime
	 */
	public static function getRowDateTime($userFields, $dateField, $dateFormat = null, $timeField = null, $timeFormat = null, $timeZone = null) {
		$dateValue = Billrun_Util::getIn($userFields, $dateField, null);
		$timeZoneValue = null;
		if (is_null($dateValue)) {
			return null;
		}
		if (!empty($timeZone)) {
			if (!empty($value = billrun_util::getIn($userFields, $timeZone))) {
				$timeZoneValue = new DateTimeZone($value);
			} else $timeZoneValue = null;
		}
		if (Billrun_Util::IsUnixTimestampValue($dateValue)) {
			$dateIntValue = intval($dateValue);
			$datetime  = date_create_from_format('U.u', $dateIntValue . "." . round(($dateValue - $dateIntValue) * 1000));
			return $datetime;
		}
		$withTimeField = false;
		if (!empty($timeField) && !is_null(Billrun_Util::getIn($userFields, $timeField))) {
			$dateValue .= ' ' . Billrun_Util::getIn($userFields, $timeField);
			$withTimeField = true;
		}
		if (!empty($dateFormat)) {
			if (!empty($timeFormat)) {
				$dateFormat .= ' ' . $timeFormat;
			} else if ($withTimeField) {
				return null;
			}
			if (!is_null($timeZoneValue)) {
				return DateTime::createFromFormat($dateFormat, $dateValue, $timeZoneValue);
			} else {
				return DateTime::createFromFormat($dateFormat, $dateValue);
			}
		} else {
			$date = !is_null($timeZoneValue) ? strtotime($dateValue .' ' .$timeZoneValue->getName()) : strtotime($dateValue);
			$datetime = new DateTime();
			$datetime->setTimestamp($date);
			if (!is_null($timeZoneValue)) {
				$datetime->setTimezone($timeZoneValue);
			}
			return $datetime;
		}
		return null;
	}
}
