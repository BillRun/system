<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Billrun_Utils_Cycle {
	public static function isTimeWithinCycle($time,$cycleKey) {
		$cycle = new Billrun_DataTypes_CycleTime($cycleKey);
		$timestamp = $time instanceof MongoDate ? $time->sec : $time;
		return $cycle->start() <= $timestamp && $timestamp <= $cycle->end();
	}

	public static function shouldBeInCycle($entryConfig,\Billrun_DataTypes_CycleTime $cycle) {
		$activationDate= !empty($entryConfig['activation_date']) ? $entryConfig['activation_date']->sec : $cycle->start();
		$startDate = empty(($entryConfig['recurrence']['start'])) 	?
									$activationDate :
									strtotime(date("Y-{$entryConfig['recurrence']['start']}-01 00:00:00", $activationDate));

		return empty($entryConfig['recurrence']['frequency']) ||  (Billrun_Plan::getMonthsDiff(date(Billrun_Base::base_dateformat,$startDate), date(Billrun_Base::base_dateformat,$cycle->end())) % $entryConfig['recurrence']['frequency']) == 0;

	}

}
