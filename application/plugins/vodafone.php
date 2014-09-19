<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
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

	public function beforeUpdateSubscriberBalance($balance, $row, $rate, $calculator) {
		$this->line_time = $row['urt']->sec;
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
	 * @todo need to verify when lines does not come in chronological order
	 */
	public function triggerPlanGroupRateRule(&$rateUsageIncluded, $groupSelected, $limits, $plan, $usageType, $rate, $subscriberBalance) {
		if ($groupSelected != 'VF') {
			return;
		}
		$sid = $subscriberBalance['sid'];
		$line_year = date('Y', $this->line_time);
		// todo: query on local datetime (abroad country time)
		$from = strtotime(str_replace('%Y', $line_year, $limits['period']['from']) . ' 00:00:00');
		$to = strtotime(str_replace('%Y', $line_year, $limits['period']['to']) . ' 23:59:59');
		$match = array(
			'$match' => array(
				'sid' => $sid,
				'plan' => $plan->getData()->get('name'),
				'urt' => array(
					'$gte' => new MongoDate($from),
					'$lte' => new MongoDate($to),
				),
				'arategroup' => $groupSelected,
				'in_group' => array(
					'$gt' => 0,
				)
			),
		);
		$group = array(
			'$group' => array(
				'_id' => array(
					'month' => array(
						'$month' => '$urt'
					),
					'day' => array(
						'$dayOfMonth' => '$urt'
					),
				),
				'c' => array(
					'$sum' => 1,
				),
			),
		);

		$results = Billrun_Factory::db()->linesCollection()->aggregate($match, $group);
		if (empty($results) || !is_array($results) || !count($results) || $results == array(0)) {
			return;
		}
		$count_days = count($results);
		if ($count_days < $limits['days']) {
			return;
		}
		// month and day without leading zero
		$line_month = date('n', $this->line_time);
		$line_day = date('j', $this->line_time);
		foreach ($results as $res) {
			if ($res['_id']['month'] == $line_month && $res['_id']['day'] == $line_day) {
				// day already exists as part of the limit
				return ;
			}
		}
		$rateUsageIncluded = 0; // user passed its limit; no more usage available
	}

}
