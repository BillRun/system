<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing interface Alertor class
 *
 * @package  calculator
 * @since    0.5
 */
abstract class Billrun_Alert extends Billrun_Base {

	/**
	 * execute aggregate
	 */
	abstract public function aggregate();

	/**
	 * go over the aggregated data and check of values the break a ceartain threshold.
	 * @return Array|bool	false if no threshold was crossed or
	 * 						an array conatining the crossed thresholds and thier values
	 */
	abstract public function getAlerts();

	/**
	 * Handle crossed threshold accordingly (trigger events,log events,etc..)
	 * @param Array		$thresholds the value returned from getThresholds function.
	 * 
	 */
	abstract public function handleAlerts($thresholds);
}
