\<?php

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

	/**
	 * Merge subscriber revisions into one revision  by a given  rules
	 **/
	public static function mergeCycleRevisions($mainRevision, $revisionsToMerge, $mergeRules = []) {
		$generalMergeRules = [
				'base' => [
					'plan_activation' => ['$min'],
					'plan_deactivation' => ['$max'],
					'from' => ['$min'],
					'to' => ['$max'],
					'services' => ['$addToSet']
					],
				'plans' => [
						'plan_activation' => ['$min'],
						'plan_deactivation' => ['$max'],
						'from' => ['$min'],
						'to' => ['$max'],

					]
				];

		$mergeRules = empty($mappingRules) ? $generalMergeRules : $mergeRules;
		foreach($revisionsToMerge as  $secRevision) {
			//Merge the main revisions porperties
			$mainRevision = Billrun_Util::mergeArrayByRules($mainRevision,$secRevision, $mergeRules['base']);
			//Merge the  resion  plans dates  entries
			if((!empty($mainRevision['plans'])  || !empty($secRevision['plans'])) && !empty($mergeRules['plans'])) {
				$mergedPlans = array_merge($mainRevision['plans'],$secRevision['plans']);
				for($planIdx=0; $planIdx < count($mergedPlans); $planIdx++ ) {
					$mainRevision['plans'] = [ Billrun_Util::mergeArrayByRules($mainRevision['plans'][0],$mergedPlans[$planIdx], $mergeRules['plans']) ] ;
				}
			}
		}
		return $mainRevision;
	}
}
