<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Vodafone plugin for vodafone special rates
 *
 * @package  Application
 * @subpackage Plugins
 * @since    2.8
 */
class vodafonePlugin extends Billrun_Plugin_BillrunPluginBase {
	
	protected $transferDaySmsc;
	protected $line_time = null;
	protected $line_type = null;
	protected $cached_results = array();
	protected $count_days;
	protected $premium_ir_not_included = null;
	protected $limit_count = 0 ;

	
	public function __construct() {
		$this->transferDaySmsc = Billrun_Factory::config()->getConfigValue('billrun.tap3_to_smsc_transfer_day', "20170301000000");
	}
	
	public function beforeUpdateSubscriberBalance($balance, $row, $rate, $calculator) {
		if ($row['type'] == 'tap3' || isset($row['roaming'])) {
			if (isset($row['urt'])) {
				$timestamp = $row['urt']->sec;
				$this->line_type = $row['type'];
				$this->line_time = date("YmdHis",  $timestamp);
			} else {
				Billrun_Factory::log()->log('urt wasn\'t found for line ' . $row['stamp'] . '.', Zend_Log::ALERT);
			}
		}
		if (!empty($row['premium_ir_not_included'])) {
			$this->premium_ir_not_included = $row['premium_ir_not_included'];
		} else {
			$this->premium_ir_not_included = null;
		}
		$this->limit_count = 0;
	}

	public function afterUpdateSubscriberBalance($row, $balance, &$pricingData, $calculator) {
		if (!is_null($this->count_days) && empty($this->premium_ir_not_included)) {
			$pricingData['vf_count_days'] = $this->count_days;
		}
		$this->count_days = NULL;
		$this->limit_count = 0;
	}

	public function checkPackageRules(&$legitimate, $package, $row, $plan, $usageType, $rate, $subscriberBalance) {
		$planPackage = $plan->get('include.groups.'.$package['service_name']);
		if(empty($planPackage)) {
			@Billrun_Factory::log("VF plguin: couldn't find package : {$package['service_name']} in plan : {$plan['name']}");
		}
		//retrun is the package  valid for VF?
		if( empty($planPackage) || empty($planPackage['limits']['vf'])|| empty($planPackage['limits']['days']) ) {
			return;
		}
		//if the line isn't under the package then it`s not legitimate
// 		$legitimate = $this->lineTime >= strtotime($package['from_date']) &&  $this->lineTime  < strtotime(date('%Y-12-31',strtotime($package['to_date'])));
// 		if(!$legitimate ) {
// 			return;
// 		}

		$sidDayCount = $this->getSidDaysCount($subscriberBalance['sid'], $planPackage['limits'], $plan, ['VF',$package['service_name']]);
		$this->limit_count += $planPackage['limits']['days'];

		if ($sidDayCount > $this->limit_count) {
			$legitimate = false;
		}
	}
	
	/**
	 * method to override the plan group limits
	 * 
	 * @param type $rateUsageIncluded
	 * @param type $groupSelected
	 * @param type $limits
	 * @param type $plan
	 * @param type $usageType
	 * @param type $rate
	 * @param type $subscriberBalance
	 * 
	 */
	public function planGroupRule(&$rateUsageIncluded, &$groupSelected, $limits, $plan, $usageType, $rate, $subscriberBalance) {
		if ($groupSelected != 'VF' || !isset($this->line_type)) {
			return;
		}
		if (!empty($this->premium_ir_not_included)) {
			$groupSelected = FALSE;
			return;
		}
		if ($this->line_type == 'tap3' && $usageType == 'sms' && $this->line_time >= $this->transferDaySmsc) {
			return;
		}

		$this->count_days = $this->getSidDaysCount($subscriberBalance['sid'], $limits, $plan, [$groupSelected]);
		$this->limit_count = $limits['days'];
		if ($this->count_days <= $limits['days']) {
			Billrun_Factory::log($this->count_days);
			Billrun_Factory::log($limits['days']);
			return;
		}
		
		$rateUsageIncluded = 0; // user passed its limit; no more usage available
		$groupSelected = FALSE; // we will cancel the usage as group plan when set to false groupSelected
	}

	protected function getSidDaysCount($sid, $limits, $plan, $groupsSelected) {
		$line_year = substr($this->line_time, 0, 4);
		$line_month = substr($this->line_time, 4, 2);
		$line_day = substr($this->line_time, 6, 2);
		$dayKey = $line_year . $line_month . $line_day;

		$results = [];
		if (!isset($this->cached_results[$sid][$line_year]) ) {
			$queryResults = $this->loadSidLines($sid, $limits, $plan, $groupsSelected);
			Billrun_Factory::log(print_r($queryResults,1));
			foreach ($queryResults as $elem) {
					$this->cached_results[$sid][$line_year][] = $elem;
			}
		}
		if( !in_array($dayKey, $this->cached_results[$sid][$line_year])) {
			$this->cached_results[$sid][$line_year][] = $dayKey;
		}
		foreach ($this->cached_results[$sid][$line_year] as $elem) {
			if ($elem <= $dayKey) {
				$results[] = $elem;
			}
		}
		Billrun_Factory::log(print_r($results,1));
		$results = array_unique($results);
		return count($results);
	}
	
	protected function loadSidLines($sid, $limits, $plan, $groupsSelected) {
		$year = date('Y', strtotime($this->line_time));
		$line_month = intval(substr($this->line_time, 4, 2));
		$line_day = intval(substr($this->line_time, 6, 2));
		$from = strtotime(str_replace('%Y', $year, $limits['period']['from']) . ' 00:00:00');
		$to = strtotime(str_replace('%Y', $year, $limits['period']['to']) . ' 23:59:59');
		$line_year = intval($year);
		$start_of_year = new MongoDate($from);
		$end_of_year = new MongoDate($to);
 		$isr_transitions = Billrun_Util::getTimeTransitionsByTimezone();
 		if (count($isr_transitions) != 3){
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
					array('type' => "tap3"),
					array('type' => "smsc"),
				),
				'plan' => $plan->getData()->get('name'),
				'$or' => [
							['arategroup' => [ '$in'=> $groupsSelected ]],
							['arategroups.service_name' => [ '$in'=> $groupsSelected ]]
						],
				'in_group' => array(
					'$gt' => 0,
				),
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
				'in_group' => 1,
				'aprice' => 1,
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
							'$add' => array('$urt', $winter_offset  * 1000) 
						),
				 
					),		
				),
			),
		);
		
		$match2 = array(
			'$match' => array(
				'urt' => array(
					'$gte' => $start_of_year,
					'$lte' => $end_of_year,
				),
			),
		);

		$group = array(
			'$group' => array(
				'_id' => array(
					'day_key' => array( 
						'$dayOfMonth' => '$isr_time', 
					),
					'month_key' => array(
						'$month' => '$isr_time', 
					),
					'year_key' => array(
						'$year' => '$isr_time', 
					),
				),
			),
		);
					
		$match3 = array(
			'$match' => array(
				'$or' => array(
					array('_id.month_key' => array('$lt'=> $line_month)),
					array(
						'$and' => array(
							array('_id.month_key' => array('$eq' => $line_month)),
							array('_id.day_key' => array('$lte' => $line_day)),
						),
					),	
				),
			),
		);

		$results = Billrun_Factory::db()->linesCollection()->aggregate($match, $project, $match2, $group, $match3);
		return $this->handleResultPadding($results);
	}
	
	protected function handleResultPadding($results){
		return array_map(function($res) {
					$month_day = "";
					if (strlen($res['_id']['month_key']) < 2) {
						$month_day .= "0";
					}
					$month_day .= $res['_id']['month_key'];
					if (strlen($res['_id']['day_key']) < 2) {
						$month_day .= "0";
					}
					$month_day .= $res['_id']['day_key'];
					return  $res['_id']['year_key'] . $month_day;
				}, $results);		
	}
	
}
