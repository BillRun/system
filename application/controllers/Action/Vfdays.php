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
		$results = $this->count_days($sid, $year, $max_datetime);
		Billrun_Factory::log()->log("{$sid} -  Quering Locally done : ".time(), Zend_Log::INFO);
		$tap3_results = $this->count_days_tap3($sid, $year, $max_datetime);
		Billrun_Factory::log()->log(" {$sid} - Quering remote done : ".time(), Zend_Log::INFO);

		$days = empty($results['VF']["count"]) ? 0 :$results['VF']["count"];
		$tap3_vf_count = empty($tap3_results['VF']["day_sum"]) ? 0 :$tap3_results['VF']["day_sum"];
		$addon_max_days = max($tap3_results['IRP_VF_10_DAYS']["day_sum"],$results['IRP_VF_10_DAYS']["count"]);

		$max_days = max($tap3_vf_count,$days);
		$this->getController()->setOutput(array(array(
				'status' => 1,
				'desc' => 'success',
				'input' => $request->getRequest(),
				'details' => array(
					'days' => $max_days,
					"days_addon"=>$addon_max_days
// 					'min_day' => 45,
// 					'max_day' => 45,
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
		$vfrateGroups = Billrun_Factory::config()->getConfigValue('vfdays.fraud.groups.vodafone',['VF','IRP_VF_10_DAYS']);

		$match1 = array(
			'$match' => array(
				'$or' => array(
					array('subscriber_id' => $sid),
					array('sid' => $sid),
				)
			),
		);
		$match2 = array(
			'$match' => array(
				'$or' => array(
					array('arategroup' => [ '$in' => $vfrateGroups] )
				),
				'record_opening_time' => new MongoRegex("/^$year/")
//				'$or' => array(
//					array_merge(
//						array(
//						'type' => "ggsn",
//						'record_opening_time' => new MongoRegex("/^$year/"),
//						), $ggsn_fields
//					),
//					array(
//						'type' => "nrtrde",
//						'callEventStartTimeStamp' => new MongoRegex("/^$year/"),
//						'sender' => array('$in' => $sender),
//						'$or' => array(
//							array(
//								'record_type' => "MTC",
//								'callEventDurationRound' => array('$gt' => 0), // duration greater then 0 => call and not sms
//							),
//							array(
//								'record_type' => "MOC",
//								'connectedNumber' => new MongoRegex('/^972/')
//							),
//						),
//					),
//				),
			),
		);

		if (!empty($max_datetime)) {
			$match2['$match']['record_opening_time'] = array(
				'$lte' => date('YmdHis', strtotime($max_datetime)),
				'$gte' => $year,
			);
		}

		$group = array(
			'$group' => array(
				'_id' => [
							'date' =>['$substr' => [
								'$record_opening_time',
								4,
								4
							]],
							'arategroup' => '$arategroup'
				],
				'count' => array('$sum' => 1),
			),
		);

		$group2 = array(
			'$group' => array(
				'_id' => '$_id.arategroup',
				'count' => array('$sum' => 1),
			),
		);

		$results = Billrun_Factory::db()->linesCollection()->aggregate($match1, $match2, $group, $group2);
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
					'isr_time' => array(
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
						'day_key' => array(
							'$dayOfMonth' => array('$isr_time'),
						),
						'month_key' => array(
							'$month' => array('$isr_time'),
						),
						'arategroup' => '$arategroup'
					),
				),
			);
			$group2 = array(
				'$group' => array(
					'_id' => '$_id.arategroup',
					'day_sum' => array(
						'$sum' => 1,
					),
				),
			);
			$billing_connection = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue('billing.db'))->linesCollection();
			$results = $billing_connection->aggregate($match, $project, $match2, $group, $group2);
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
