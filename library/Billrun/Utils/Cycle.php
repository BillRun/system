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
	public function mergeSubRevisions($mainRevision, $revisionsToMerge, $mergeRules = []) {
		$generalMergeRules = [
				'plan_activation' => ['$min'],
				'plan_deactivation' => ['$max'],
				'from' => ['$min'],
				'to' => ['$max'],
				'services' => ['$addToSet']
				];
		$mergeRules = empty($mappingRules) ? $generalMergeRules : $mergeRules;
		foreach($revisionsToMerge as  $secRevision) {
			$mainRevision = Billrun_Util::mergeArrayByRules($mainRevision,$secRevision, $mergeRules);
		}
		return $mainRevision;
	}
}
