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
class Vfdays2Action extends Action_Base {

	protected $plans = null;
	/**
	 * method to execute the refund
	 * it's called automatically by the api main controller
	 * on vadofone
	 */
	public function execute() {
		Billrun_Factory::log()->log("Execute ird days API call", Zend_Log::INFO);
		$request = $this->getRequest();
		$min_days = intval($request->get("min_days"));
		if (empty($min_days)) {
			$this->getController()->setOutput(array(array(
				'status' => 0,
				'desc' => 'need to supply min_days arguments',
				'input' => $request->getRequest(),
			)));
			return;
		}
		$list = $this->count_days_by_lines($min_days);
		$this->getController()->setOutput(array(array(
			'status' => 1,
			'desc' => 'success',
			'input' => $request->getRequest(),
			'details' => $list
		)));
	}
	
	protected function count_days_by_lines($min_days = 35) {
		$elements = array();
		$elements[] = array(
			'$match' => array(
				'unified_record_time' => array(
					'$gte' => new MongoDate(strtotime('yesterday midnight')),
					'$lte' => new MongoDate(strtotime('midnight'))
				),
				'vf_count_days' => array(
					'$gte' => $min_days,
				)
			),
		);
		
		$elements[] = array(
			'$group' => array(
				'_id' => '$sid',
				'count_days' => array(
					'$max' => '$vf_count_days'
				),
			)
		);
		
		$elements[] = array(
			'$project' => array(
				'_id' => false,
				'sid' => '$_id',
				'count_days' => '$count_days'
			)
		);
		
		$res = call_user_func_array(array(Billrun_Factory::db()->linesCollection(), 'aggregate'), $elements);
		return $res;
	}

}
