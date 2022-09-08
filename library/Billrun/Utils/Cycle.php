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
