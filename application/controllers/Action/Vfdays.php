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
		$plans = Billrun_Factory::config()->getConfigValue('vfdays.target_plans');
		Billrun_Factory::log()->log("{$sid} - Quering : ".time(), Zend_Log::INFO);
		$results = Utils_VF::countVFDays(Billrun_Factory::db()->linesCollection(), $sid, $year, $max_datetime);
		Billrun_Factory::log()->log("{$sid} -  Quering Locally done : ".time(), Zend_Log::INFO);
		//$tap3_results = $this->count_days_tap3($sid, $year, $max_datetime);
		$billingLinesColl = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue('billing.db'))->linesCollection();
		$tap3_results = Utils_VF::countVFDays($billingLinesColl, $sid, $year, $max_datetime, [	'$or' => [
																									['type' => 'tap3'],
																									['type' => 'smsc'],
																									['type' => "nsn","roaming"=>true],
																								],
																							'plan' => ['$in' => $plans]]);
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

}
