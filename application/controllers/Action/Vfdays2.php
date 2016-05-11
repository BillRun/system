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
		$datetime = strval($request->get("datetime"));
		$offset_days = intval($request->get("offset_days", 1));
		$list = $this->count_days_by_lines($min_days, $datetime, $offset_days);
		$this->getController()->setOutput(array(array(
			'status' => 1,
			'desc' => 'success',
			'input' => $request->getRequest(),
			'details' => $list
		)));
	}
	
	protected function count_days_by_lines($min_days = 35, $datetime = null, $offset_days = 1) {
		if (empty($datetime)) {
			$unix_datetime = time();
		} else {
			$unix_datetime = strtotime($datetime);
		}
		
		if (!($offset_days >= 1 && $offset_days <= 7)) {
			$offset_days = 1;
		}

		
		$start = strtotime('-' . (int) $offset_days . ' days midnight', $unix_datetime);
		$end = strtotime('midnight', $unix_datetime);
		$elements = array();
		$elements[] = array(
			'$match' => array(
				'unified_record_time' => array(
					'$gte' => new MongoDate($start),
					'$lte' => new MongoDate($end),
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
					'$max' => '$vf_count_days',
				),
				'last_usage_time' => array(
					'$max' => '$unified_record_time',
				),
			)
		);
		
		$elements[] = array(
			'$project' => array(
				'_id' => false,
				'sid' => '$_id',
				'count_days' => '$count_days',
				'last_usage_time' => array(
					'$dateToString' => array(
						'format' => '%Y-%m-%d %H:%M:%S',
						'date' => array(
							'$add' => array('$last_usage_time', date('Z') * 1000)
						)
					)
				),
				'min_days' => array(
					'$literal' => $min_days,
				),
			)
		);
		
		$res = call_user_func_array(array(Billrun_Factory::db()->linesCollection(), 'aggregate'), $elements);
		return $res;
	}

}
