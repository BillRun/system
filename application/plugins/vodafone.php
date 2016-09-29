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

	protected $line_time = null;
	protected $line_type = null;
	protected $cached_results = array();
	protected $count_days;

	public function beforeUpdateSubscriberBalance($balance, $row, $rate, $calculator) {
		if (isset($row['type'])) {
			if (isset($row['urt'])) {
				$timestamp = $row['urt']->sec;
				$this->line_type = $row['type'];
				$this->line_time = date("YmdHis",  $timestamp);	
			} else {
				Billrun_Factory::log()->log('urt wasn\'t found for line ' . $row['stamp'] . '.', Zend_Log::ALERT);
			}
		}	
	}
	
	public function afterUpdateSubscriberBalance($row, $balance, &$pricingData, $calculator) {
		if (!is_null($this->count_days)) {
			$pricingData['vf_count_days'] = $this->count_days;
		}
		$this->count_days = NULL;
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
		$sid = $subscriberBalance['sid'];
		$line_year = substr($this->line_time, 0, 4);
		$line_month = substr($this->line_time, 4, 2);
		$line_day = substr($this->line_time, 6, 2);
		$dayKey = $line_year . $line_month . $line_day;
		if($this->line_type == 'tap3') {
			$results = $this->loadSidLines($sid, $limits, $plan, $groupSelected);
		} else if ($this->line_type == 'nrtrde' || $this->line_type == 'ggsn') {
			$results = $this->loadSidNrtrdeLines($sid, $limits, $plan, $groupSelected);
		}
		if (!isset($this->cached_results[$sid]) || !in_array($dayKey, $this->cached_results[$sid])) {
			$this->cached_results[$sid][] = $dayKey;
		}
		foreach ($this->cached_results[$sid] as $elem) {
			if ($elem <= $dayKey) {
				$results[] = $elem;
			}
		}
		$results = array_unique($results);

		$this->count_days = count($results);
		if ($this->count_days <= $limits['days']) {
			return;
		}
		
		$rateUsageIncluded = 0; // user passed its limit; no more usage available
		$groupSelected = FALSE; // we will cancel the usage as group plan when set to false groupSelected
	}

	protected function loadSidLines($sid, $limits, $plan, $groupSelected) {
		$year = date('Y', strtotime($this->line_time));
		$line_month = intval(substr($this->line_time, 4, 2));
		$line_day = intval(substr($this->line_time, 6, 2));
		$from = strtotime(date('YmdHis', strtotime(str_replace('%Y', $year, $limits['period']['from']) . ' 00:00:00')));
		$to = strtotime(date('YmdHis', strtotime(str_replace('%Y', $year, $limits['period']['to']) . ' 23:59:59')));
		$line_year = intval($year);
		$start_of_year = new MongoDate($from);
		$end_of_year = new MongoDate($to);
		$isr_transitions = Billrun_Util::getIsraelTransitions();
		if (Billrun_Util::isWrongIsrTransitions($isr_transitions)){
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
				'type' => 'tap3',
				'plan' => $plan->getData()->get('name'),
				'arategroup' => $groupSelected,
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
				'isr_time' => array(
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
				'_id.day_key' => array(
					'$lte' => $line_day, 
				),
				'_id.month_key' => array(
					'$lte' => $line_month, 
				),
				'_id.year_key' => array(
					'$lte' => $line_year, 
				),
			),
		);

		$results = Billrun_Factory::db()->linesCollection()->aggregate($match, $project, $match2, $group, $match3);
		return $this->handleResultPadding($results);
	}
	
	protected function loadSidNrtrdeLines($sid, $limits, $plan, $groupSelected) {	
		$year = date('Y', strtotime($this->line_time));
		$line_month = intval(substr($this->line_time, 4, 2));
		$line_day = intval(substr($this->line_time, 6, 2));
		$from = strtotime(date('YmdHis', strtotime(str_replace('%Y', $year, $limits['period']['from']) . ' 00:00:00')));
		$to = strtotime(date('YmdHis', strtotime(str_replace('%Y', $year, $limits['period']['to']) . ' 23:59:59')));
		$line_year = intval($year);
		$start_of_year = new MongoDate($from);
		$end_of_year = new MongoDate($to);
		$isr_transitions = Billrun_Util::getIsraelTransitions();
		if (Billrun_Util::isWrongIsrTransitions($isr_transitions)){
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
				'type' => array(
					'$in' => array('nrtrde', 'ggsn')
				),
				'plan' => $plan->getData()->get('name'),
				'arategroup' => $groupSelected,
				'in_group' => array(
					'$gt' => 0,
				),
				'aprice' => array(
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
				'isr_time' => array(
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
				'_id.day_key' => array(
					'$lte' => $line_day, 
				),
				'_id.month_key' => array(
					'$lte' => $line_month, 
				),
				'_id.year_key' => array(
					'$lte' => $line_year, 
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
