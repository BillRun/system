<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * PL plugin
 *
 * @package  Application
 * @subpackage Plugins
 * @since    0.5
 */
class pelephonePlugin extends Billrun_Plugin_BillrunPluginBase {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'pelephone';
	
	/**
	 * billing row to handle
	 * use to pre-fetch the billing line if the line is not passed in the requested event
	 * 
	 * @var array
	 */
	protected $row;
	
	public function extendRateParamsQuery(&$query, &$row, &$calculator) {
		if (!in_array($row['usaget'], array('call', 'video_call', 'sms', 'mms'))) {
			return;
		}
		$current_time = date('His');
		$weektime = date('w') . '-' . $current_time;
		$current_datetime = $row['urt']->sec;
		$day_type = Billrun_HebrewCal::getDayType($current_datetime);
		if (
			($weektime >= '5-160000' && $weektime <= '6-200000') ||
			($day_type == HEBCAL_SHORTDAY && $current_time >= '160000' && $current_time <= '235959') ||
			(
				$day_type == HEBCAL_HOLIDAY && 
				(
					($current_time >= '000000' && $current_time <= '195959') || 
					(Billrun_HebrewCal::getDayType($nextday = strtotime('+1 day', $current_datetime)) == HEBCAL_HOLIDAY || date('w', $nextday) == 6)
				)
			)
		) {
			$shabbat = true;
		} else {
			$shabbat = false;
		}
		if ($this->isInterconnect($row)) {
			$interconnect = true;
		} else {
			$interconnect = false;
		}
		$query[0]['$match']['params.shabbat'] = $shabbat;
		$query[0]['$match']['params.interconnect'] = $interconnect;
	}
	
	/**
	 * method to check if billing row is interconnect (not under PL network)
	 * 
	 * @param array $row the row to check
	 * 
	 * @return boolean true if not under PL network else false
	 */
	protected function isInterconnect($row) {
		return isset($row['np_code']) && substr($row['np_code'], 0, 3) != '831'; // 831 np prefix of PL; @todo: move it to configuration
	}


	/**
	 * use to store the row to extend balance query (method extendGetBalanceQuery)
	 * 
	 * @param array $row
	 * @param Billrun_Calculator $calculator
	 */
	public function beforeCalculatorUpdateRow(&$row, Billrun_Calculator $calculator) {
		if ($calculator->getType() == 'pricing') {
			$this->row = $row;
		}
	}
	
	/**
	 * method to extend the balance
	 * 
	 * @param array $query the query that will pull the balance
	 * @param int $timeNow the time of the row (unix timestamp)
	 * @param string $chargingType
	 * @param string $usageType
	 * @param Billrun_Balance $balance
	 * 
	 * @todo change the values to be with flag taken from pp_includes into balance object
	 * 
	 */
	public function extendGetBalanceQuery(&$query, &$timeNow, &$chargingType, &$usageType, Billrun_Balance $balance) {
		if (!empty($this->row)) {
			$pp_includes_external_ids = array();
			if ($this->isInterconnect($this->row)) {
				// we are out of PL network
				array_push($pp_includes_external_ids, 7);
			}

			if (isset($this->row['call_type']) && $this->row['call_type'] == '2') {
				array_push($pp_includes_external_ids, 3, 4);
			}

			if (count($pp_includes_external_ids)) {
				$query['pp_includes_external_id'] = array('$nin' => $pp_includes_external_ids);
			}
		}
	}
	

}
