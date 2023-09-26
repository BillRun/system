<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Billrun_Utils_Cycle {
	public static function isTimeWithinCycle($time,$cycleKey) {
		$cycle = new Billrun_DataTypes_CycleTime($cycleKey);
		$timestamp = $time instanceof Mongodloid_Date ? $time->sec : $time;
		return $cycle->start() <= $timestamp && $timestamp <= $cycle->end();
	}

	public static function shouldBeInCycle($entryConfig,\Billrun_DataTypes_CycleTime $cycle) {
		$activationDate= !empty($entryConfig['activation_date']) ? $entryConfig['activation_date']->sec : $cycle->start();
		$startDate = empty(($entryConfig['recurrence']['start'])) 	?
									$activationDate :
									strtotime(date("Y-{$entryConfig['recurrence']['start']}-01 00:00:00", $activationDate));
		$startDateStr = date(Billrun_Base::base_dateformat,($startDate < $cycle->end() ? $startDate : $cycle->end()));
		$endDateStr = date(Billrun_Base::base_dateformat,($startDate < $cycle->end() ? $cycle->end() : $startDate));

		return ( !empty($entryConfig['recurrence']['frequency']) &&
				(Billrun_Utils_Time::getMonthsDiff($startDateStr, $endDateStr) % $entryConfig['recurrence']['frequency']) == 0 )
				||
				(!empty($entryConfig['deactivation_date']) && $entryConfig['deactivation_date']->sec <= $cycle->end() &&  $entryConfig['deactivation_date']->sec >= $cycle->start() )
				||
				(!empty($entryConfig['end']) && $entryConfig['end'] < $cycle->end() &&  $entryConfig['end'] > $cycle->start() );

	}

	public static function addMonthsToCycleKey($cycleKey,$monthsToAdd) {
		$year = substr($cycleKey,0,4);
		$month = substr($cycleKey,4,2);
		$year = $year + (ceil(($monthsToAdd + $month ) / 12  ) - 1);
		$month = ( (($monthsToAdd + $month + 11) % 12) + 1 );

		return $year . str_pad($month,2,'0',STR_PAD_LEFT) . substr($cycleKey,6);

	}

	public static  function substractMonthsFromCycleKey($cycleKey,$monthsTosubstract) {

		return self::addMonthsToCycleKey($cycleKey,-$monthsTosubstract);
	}

	public static function getRecurrenceOffset($recurrenceConfig, $cycleKey,$activationDate = null) {
		$activationDate = ($activationDate ?
						$activationDate :
						Billrun_Billingcycle::getStartTime($cycleKey));
		$startDate = ($recurrenceConfig['start'] ?
						date("Y-{$recurrenceConfig['start']}-01", $activationDate) :
						date(Billrun_Base::base_dateformat,$activationDate));

		return Billrun_Utils_Time::getMonthsDiff($startDate, date(Billrun_Base::base_dateformat, Billrun_Billingcycle::getEndTime($cycleKey))) % $recurrenceConfig['frequency'];
	}

	/**
	 * Get a sorted cycle month list based on the plan recurrence configuration
	 * @param $planConfig  the Plan recurence configuration
	 * @return a sorted  array (ascending) containg the legitimate cycle months
	 */
	public static function getPlanCycleMonths($planConfig) {
		if(empty($planConfig['recurrence']['frequency'])) {
			Billrun_Factory::log('getPlanCycleMonths: Incorrect configuration provided',Zend_Log::ERR);
			throw new Exception('getPlanCycleMonths: Incorrect configuration provided');
		}
		$startMonth = 	intval(!empty($planConfig['activation_date']) ?
							date('m',$planConfig['activation_date']->sec) :
							Billrun_Util::getFieldVal($planConfig['recurrence']['start'], 1 ));
		$months = [];
		for($i=0; $i < 12; $i += $planConfig['recurrence']['frequency']) {
			$months[] = ($i + $startMonth - 1) % 12 + 1;
		}
		asort( $months );

		return $months;
	}

	/**
	 * Merge subscriber revisions into one revision  by a given  rules
	 **/
	public static function mergeCycleRevisions($mainRevision, $revisionsToMerge, $mergeRules = []) {
		$generalMergeRules = [
				'plan_activation' => ['$min'=>1],
				'plan_deactivation' => ['$max' => 1],
				'from' => ['$min' => 1],
				'to' => ['$max' => 1],
				'services' => ['$addToSet' => 1],

				'plans' => [
							'$mergeMultiArraysByRules' =>
								[
								'plan_activation' => ['$min' => 1],
								'plan_deactivation' => ['$max' => 1],
								'from' => ['$min' => 1],
								'to' => ['$max' => 1],
								]
					]
				];

		$mergeRules = empty($mappingRules) ? $generalMergeRules : $mergeRules;
		foreach($revisionsToMerge as  $secRevision) {
			$mainRevision = Billrun_Util::mergeArrayByRules($mainRevision,$secRevision, $mergeRules);
		}
		return $mainRevision;
	}
}
