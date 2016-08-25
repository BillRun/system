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
		if ($row['type'] == 'tap3') {
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
		$results = $this->loadSidLines($sid, $limits, $plan, $groupSelected);
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
		$isr_transitions = timezone_transitions_get(new DateTimeZone('Asia/Jerusalem'), strtotime('January 1st'), strtotime('December 31'));
		$summer_transition = $isr_transitions['1']['time'];
		$winter_transition = $isr_transitions['2']['time'];	
		$summer_offset = $isr_transitions['1']['offset'];
		$winter_offset = $isr_transitions['2']['offset'];
		$summer_date = new DateTime($summer_transition);
		$winter_date = new DateTime($winter_transition);
		$transition_date_summer = new MongoDate($summer_date->getTimestamp());
		$transition_date_winter = new MongoDate($winter_date->getTimestamp());
			
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
		
		$match = array(
			'$match' => array(
				'sid' => $sid,
				'type' => 'tap3',
				'plan' => $plan->getData()->get('name'),
				'urt' => array(
					'$gte' => $start_of_year,
					'$lte' => $end_of_year,
				),
				'arategroup' => $groupSelected,
				'in_group' => array(
					'$gt' => 0,
				),
				'billrun' => array(
					'$exists' => true,
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
		
		$match2 = array(
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

		$results = Billrun_Factory::db()->linesCollection()->aggregate($project, $match, $group, $match2);
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
