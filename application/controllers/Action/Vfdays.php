<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Refund action class
 *
 * @package  Action
 * @since    1.0
 */
class VfdaysAction extends Action_Base {

	protected $plans = null;

	/**
	 * method to execute the refund
	 * it's called automatically by the api main controller
	 * on vadofone
	 */
	public function execute() {
		Billrun_Factory::log()->log("Execute ird days API call", Zend_Log::INFO);
		$request = $this->getRequest();
		$sid = intval($request->get("sid"));
		$year = intval($request->get("year"));
		if (is_null($year) || empty($year)) {
			$year = date("Y");
		}
		$max_datetime = $request->get("max_datetime");
		$this->plans = Billrun_Factory::config()->getConfigValue('vfdays.target_plans');
		Billrun_Factory::log()->log("{$sid} - Quering : ".time(), Zend_Log::INFO);
		$results = Utils_VF::countVFDays(Billrun_Factory::db()->linesCollection(), $sid, $year, $max_datetime);
		Billrun_Factory::log()->log("{$sid} -  Quering Locally done : ".time(), Zend_Log::INFO);
		$tap3_results = $this->count_days_tap3($sid, $year, $max_datetime);
		Billrun_Factory::log()->log(" {$sid} - Quering remote done : ".time(), Zend_Log::INFO);

		$days = empty($results['VF']["day_sum"]) ? 0 :$results['VF']["day_sum"];
		$tap3_vf_count = empty($tap3_results['VF']["day_sum"]) ? 0 :$tap3_results['VF']["day_sum"];
		$addon_max_days = max(0,@$tap3_results['IRP_VF_10_DAYS']["day_sum"],@$results['IRP_VF_10_DAYS']["day_sum"]);

		$max_days = max($tap3_vf_count,$days);
		$this->getController()->setOutput(array(array(
				'status' => 1,
				'desc' => 'success',
				'input' => $request->getRequest(),
				'details' => array(
					'days' => $max_days,
					"days_addon"=>$addon_max_days
				)
		)));
	}

	/**
	 * for subscriber with LARGE_PREIUM (?KOSHER) counts the number of days he used he's phone abroad
	 * in the current year based on fraud lines 
	 * @param type $sid
	 * @return number of days 
	 */
	public function count_days($sid, $year = null, $max_datetime = null) {

//		$ggsn_fields = Billrun_Factory::config()->getConfigValue('ggsn.fraud.groups.vodafone15');
//		$sender = Billrun_Factory::config()->getConfigValue('nrtrde.fraud.groups.vodafone15');
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


			$match = array(
				'$match' => array(
					'sid' => $sid,
					'arategroup' => ['$in'=> $vfRateGroups ],
				),
			);

			$project = array(
				'$project' => array(
					'sid' => 1,
					'urt' => 1,
					'type' => 1,
					'plan' => 1,
					'arategroup' => 1,
					'billrun' => 1,
					'urt' => array(
						'$cond' => array(
							'if' => array(
								'$and' => array(
									array('$gte' => array('$urt', $transition_date_summer)),
									array('$lt' => array('$urt', $transition_date_winter)),
								),
							),
							'then' => array(
								'$add' => array('$urt', $summer_offset * 1000)
							),
							'else' => array(
								'$add' => array('$urt', $winter_offset * 1000)
							),
						),
					),
				),
			);

			$match2 = array(
				'$match' => array(
					'urt' => array(
						'$gte' => $start_of_year,
						'$lte' => $end_date,
					),
				),
			);
			$group = array(
				'$group' => array(
					'_id' => array(
						'plan'=> '$plan',
						'date' =>['$dateToString'=>['format' => '%Y-%j','date'=>'$urt']],
						'arategroup' => '$arategroup'
					),
				),
			);
			$group2 = array(
				'$group' => array(
					'_id' => [
						'arategroup' =>'$_id.arategroup',
						'plan'=>'$_id.plan'
					],
					'max_date' => ['$max'=>'_id.$date' ],
					'day_sum' => array(
						'$sum' => 1,
					),
				),
			);
			$sortPlans = [
				'$sort' => ['max_date'=> -1]
			];

			$group3 = array(
				'$group' => array(
					'_id' => '$_id.arategroup',
					'day_sum' => array(
						'$max' => '$day_sum',
					),
				),
			);
			$billing_connection = Billrun_Factory::db()->linesCollection();
			Billrun_Factory::log("vfdays tap3 aggregate query : ".json_encode([$match, $project, $match2, $group, $group2,$sortPlans,$group3]));
			$results = $billing_connection->aggregate($match, $project, $match2, $group, $group2,$sortPlans,$group3);
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

	public function count_days_tap3($sid, $year = null, $max_datetime = null) {
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


			$match = array(
				'$match' => array(
					'sid' => $sid,
					'$or' => array(
						array('type' => 'tap3'),
						array('type' => 'smsc'),
						array('type' => "nsn","roaming"=>true),
					),
					'plan' => array('$in' => $this->plans),
					'arategroup' => ['$in'=> $vfRateGroups ],
					'billrun' => array(
						'$exists' => true,
					),
				),
			);

			$project = array(
				'$project' => array(
					'sid' => 1,
					'urt' => 1,
					'type' => 1,
					'plan' => 1,
					'arategroup' => 1,
					'billrun' => 1,
					'urt' => array(
						'$cond' => array(
							'if' => array(
								'$and' => array(
									array('$gte' => array('$urt', $transition_date_summer)),
									array('$lt' => array('$urt', $transition_date_winter)),
								),
							),
							'then' => array(
								'$add' => array('$urt', $summer_offset * 1000)
							),
							'else' => array(
								'$add' => array('$urt', $winter_offset * 1000)
							),
						),
					),
				),
			);

			$match2 = array(
				'$match' => array(
					'urt' => array(
						'$gte' => $start_of_year,
						'$lte' => $end_date,
					),
				),
			);
			$group = array(
				'$group' => array(
					'_id' => array(
						'plan'=> '$plan',
						'date' =>['$dateToString'=>['format' => '%Y-%j','date'=>'$urt']],
						'arategroup' => '$arategroup'
					),
				),
			);
			$group2 = array(
				'$group' => array(
					'_id' => [
						'arategroup' =>'$_id.arategroup',
						'plan'=>'$_id.plan'
					],
					'max_date' => ['$max'=>'_id.$date' ],
					'day_sum' => array(
						'$sum' => 1,
					),
				),
			);
			$sortPlans = [
				'$sort' => ['max_date'=> -1]
			];

			$group3 = array(
				'$group' => array(
					'_id' => '$_id.arategroup',
					'day_sum' => array(
						'$max' => '$day_sum',
					),
				),
			);
			$billing_connection = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue('billing.db'))->linesCollection();
			Billrun_Factory::log("vfdays tap3 aggregate query : ".json_encode([$match, $project, $match2, $group, $group2,$sortPlans,$group3]));
			$results = $billing_connection->aggregate($match, $project, $match2, $group, $group2,$sortPlans,$group3);
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
