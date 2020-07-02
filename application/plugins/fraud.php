<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Fraud plugin
 *
 * @package  Application
 * @subpackage Plugins
 * @since    0.5
 */
class fraudPlugin extends Billrun_Plugin_Base {
	
	protected static $minutesIntervals = [15, 30];

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'fraud';
	
	public function cronMinute() {
		self::runMinutelyFrauds();
	}

	public function cronHour() {
		self::runHourlyFrauds();
	}
	
	public static function runMinutelyFrauds() {
		$currentMinute = date('i');
		if ($currentMinute == 0) {
			$currentMinute = 60;
		}
		$minutesToRun = [];
		foreach (self::$minutesIntervals as $minuteInterval) {
			if ($currentMinute % $minuteInterval == 0) {
				$minutesToRun[] = $minuteInterval;
			}
		}
		if (!empty($minutesToRun)) {
			$params = [
				'recurrenceType' => 'minutely',
				'recurrenceValues' => $minutesToRun,
			];
			Billrun_Factory::fraudManager()->run($params);
		}
	}

	public static function runHourlyFrauds() {
		$currentHour = date('H');
		if ($currentHour == 0) {
			$currentHour = 24;
		}
		$houresToRun = [];
		for ($i = 1; $i <= 24; $i++) {
			if ($currentHour % $i == 0) {
				$houresToRun[] = $i;
			}
		}
		if (!empty($houresToRun)) {
			$params = [
				'recurrenceType' => 'hourly',
				'recurrenceValues' => $houresToRun,
			];
			Billrun_Factory::fraudManager()->run($params);
		}
	}

}
