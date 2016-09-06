<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Trait used to condition the running of an action on the charge day.
 *
 * @package  Billing
 * @since    5.0
 */
trait Billrun_Traits_OnChargeDay {
	
	/**
	 * Get the current date with the current tenant's timezone.
	 * @return \DateTime Current time with the tenants timezone.
	 * @throws Exception on invalid timezone.
	 * @todo Move this function to a more fitting location.
	 */
	protected function getTimezoneDate() {
		$timezone = Billrun_Factory::config()->getConfigValue('billrun.timezone');
		
		// Throws exception.
		$dateTimeZone = new DateTimeZone($timezone);
		
		return new DateTime(null, $dateTimeZone);
	}
	
	/**
	 * Check if today is the charge day.
	 * @param int $hourLag - Earliest hour to be accepted as charge day.
	 * For example if $hourLag is 3, charge day before 3AM will not count as
	 * the actual charge day.
	 * @return true if today is the charge day.
	 */
	protected function isChargeDay($hourLag = 0) {
		$date = $this->getTimezoneDate();
		
		// Check the cycle date.
		$cycleDay = Billrun_Factory::config()->getConfigValue('billrun.charging_day');
		
		// Cycle occurs only on the cycle day, with a three hours lag.
		return (($date->format('d') == $cycleDay) && 
				($date->format('H') >= $hourLag));
	}
}
