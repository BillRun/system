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
			if (isset($row['basicCallInformation']['CallEventStartTimeStamp']['localTimeStamp'])) {
				$this->line_type = $row['type'];
				$this->line_time = $row['basicCallInformation']['CallEventStartTimeStamp']['localTimeStamp'];
			} else {
				Billrun_Factory::log()->log('localTimeStamp wasn\'t found for line ' . $row['stamp'] . '.', Zend_Log::ALERT);
			}
		} else if ($row['type'] == 'nrtrde' || $row['type'] == 'ggsn') {
			if (isset($row['callEventStartTimeStamp'])) {
				$this->line_type = $row['type'];
				$this->line_time = $row['callEventStartTimeStamp'];
			} else {
				Billrun_Factory::log()->log('localTimeStamp wasn\'t found for line ' . $row['stamp'] . '.', Zend_Log::ALERT);
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
			$results = $this->loadSidLines($sid, $limits, $plan, $groupSelected, $dayKey);
		} else if ($this->line_type == 'nrtrde' || $this->line_type == 'ggsn') {
			$results = $this->loadSidNrtrdeLines($sid, $limits, $plan, $groupSelected, $dayKey);
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

	protected function loadSidLines($sid, $limits, $plan, $groupSelected, $dayKey) {
		$line_year = date('Y', strtotime($this->line_time));
		$from = date('YmdHis', strtotime(str_replace('%Y', $line_year, $limits['period']['from']) . ' 00:00:00'));
		$to = date('YmdHis', strtotime(str_replace('%Y', $line_year, $limits['period']['to']) . ' 23:59:59'));
		$match = array(
			'$match' => array(
				'sid' => $sid,
				'type' => 'tap3',
				'plan' => $plan->getData()->get('name'),
				'basicCallInformation.CallEventStartTimeStamp.localTimeStamp' => array(
					'$gte' => $from,
					'$lte' => $to,
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
						'$substr' => array('$basicCallInformation.CallEventStartTimeStamp.localTimeStamp', 0, 8),
					),
				),
			),
		);
		$match2 = array(
			'$match' => array(
				'_id.day_key' => array(
					'$lte' => $dayKey,
				),
			),
		);

		$results = Billrun_Factory::db()->linesCollection()->aggregate($match, $group, $match2);
		return array_map(function($res) {
					return $res['_id']['day_key'];
				}, $results);
	}
	
	protected function loadSidNrtrdeLines($sid, $limits, $plan, $groupSelected, $dayKey) {
		$line_year = date('Y', strtotime($this->line_time));
		$from = date('YmdHis', strtotime(str_replace('%Y', $line_year, $limits['period']['from']) . ' 00:00:00'));
		$to = date('YmdHis', strtotime(str_replace('%Y', $line_year, $limits['period']['to']) . ' 23:59:59'));
		$match = array(
			'$match' => array(
				'sid' => $sid,
				'type' => array(
					'$in' => array('nrtrde', 'ggsn')
				),
				'plan' => $plan->getData()->get('name'),
				'callEventStartTimeStamp' => array(
					'$gte' => $from,
					'$lte' => $to,
				),
				'arategroup' => $groupSelected,
				'in_group' => array(
					'$gt' => 0,
				),
				'aprice' => array(
					'$exists' => true,
				),
			),
		);
		$group = array(
			'$group' => array(
				'_id' => array(
					'day_key' => array(
						'$substr' => array('$callEventStartTimeStamp', 0, 8),
					),
				),
			),
		);
		$match2 = array(
			'$match' => array(
				'_id.day_key' => array(
					'$lte' => $dayKey,
				),
			),
		);

		$results = Billrun_Factory::db()->linesCollection()->aggregate($match, $group, $match2);
		return array_map(function($res) {
					return $res['_id']['day_key'];
				}, $results);
	}

}
