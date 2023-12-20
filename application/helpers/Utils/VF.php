<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Golan subscriber class
 *
 * @package  Bootstrap
 * @since    1.0
 * @todo refactoring to general subscriber http class
 */
class Utils_VF  {
	public static function countVFDays($coll, $sid, $year = null, $max_datetime = null,$filter = []) {
		try {
			$vfRateGroups = Billrun_Factory::config()->getConfigValue('vfdays.fraud.groups.vodafone',['VF','IRP_VF_10_DAYS']);
			$from = strtotime($year . '-01-01' . ' 00:00:00');
			if (is_null($max_datetime)) {
				$to = strtotime($year . '-12-31' . ' 23:59:59');
			} else {
				$to = !is_numeric($max_datetime) ? strtotime($max_datetime) : $max_datetime;
			}

			$start_of_year = new MongoDate($from);
			$end_date = new MongoDate($to);
			$isr_transitions = Billrun_Util::getIsraelTransitions();
			if (Billrun_Util::isWrongIsrTransitions($isr_transitions)) {
				Billrun_Log::getInstance()->log("The number of transitions returned is unexpected", Zend_Log::ALERT);
			}
			$transition_dates = Billrun_Util::buildTransitionsDates($isr_transitions);
			$transition_date_summer = new MongoDate($transition_dates['summer']->getTimestamp());
			$transition_date_winter = new MongoDate($transition_dates['winter']->getTimestamp());
			$summer_offset = Billrun_Util::getTransitionOffset($isr_transitions, 1);
			$winter_offset = Billrun_Util::getTransitionOffset($isr_transitions, 2);


			$match = [
				'$match' => [
					'sid' => $sid,
					'arategroup' => ['$in'=> $vfRateGroups ],
				],
			];

			if(!empty($filter) &&  is_array($filter)) {
				$match['$match'] = array_merge($filter,$match['$match']);
			}

			$project = [
				'$project' => [
					'sid' => 1,
					'urt' => 1,
					'type' => 1,
					'plan' => 1,
					'arategroup' => 1,
					'billrun' => 1,
					'urt' => [
						'$cond' => [
							'if' => [
								'$and' => [
									['$gte' => ['$urt', $transition_date_summer] ],
									['$lt' => ['$urt', $transition_date_winter] ],
								],
							],
							'then' => [
								'$add' => ['$urt', $summer_offset * 1000 ]
							],
							'else' => [
								'$add' => ['$urt', $winter_offset * 1000 ]
							],
						],
					],
				],
			];

			$match2 = [
				'$match' => [
					'urt' => [
						'$gte' => $start_of_year,
						'$lte' => $end_date,
					],
				],
			];
			$group = [
				'$group' => [
					'_id' => [
						'plan'=> '$plan',
						'date' =>[ '$dateToString' => ['format' => '%Y-%j','date'=>'$urt'] ],
						'arategroup' => '$arategroup'
					],
				],
			];
			$group2 = [
				'$group' => [
					'_id' => [
						'arategroup' =>'$_id.arategroup',
						'plan'=>'$_id.plan'
					],
					'max_date' => ['$max'=>'_id.$date' ],
					'day_sum' => [
						'$sum' => 1,
					],
				],
			];
			$sortPlans = [
				'$sort' => ['max_date'=> -1]
			];

			$group3 = [
				'$group' => [
					'_id' => '$_id.arategroup',
					'day_sum' => [
						'$max' => '$day_sum',
					],
				],
			];

			Billrun_Factory::log("vfdays aggregate query : ".json_encode([$match, $project, $match2, $group, $group2,$sortPlans,$group3]));
			$results = $coll->aggregate($match, $project, $match2, $group, $group2,$sortPlans,$group3);
		} catch (Exception $ex) {
			Billrun_Factory::log('Error to fetch to billing from fraud system. ' . $ex->getCode() . ": " . $ex->getMessage(), Zend_Log::ERR);
			Billrun_Factory::log('We will skip the billing fetch for this call.', Zend_Log::WARN);
		}
		$associatedResults = [];
		foreach($results as $res) {
			$associatedResults[$res['_id']] = $res;
		}
		return $associatedResults;
	}
}
