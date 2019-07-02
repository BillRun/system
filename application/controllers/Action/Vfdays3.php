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
class Vfdays3Action extends Action_Base {

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
		$max_datetime = $request->get("max_datetime");
		$list = $this->count_days_by_lines($min_days, $max_datetime);
		$this->getController()->setOutput(array(array(
				'status' => 1,
				'desc' => 'success',
				'input' => $request->getRequest(),
				'details' => $list
		)));
	}

	/**
	 * 
	 * @param int $min_days Return only subscribers exceeding this number of days
	 * @param string $max_datetime A PHP date/time string to be used as an upper limit
	 * @return array
	 */
	public function count_days_by_lines($min_days = 35, $max_datetime = null) {
		$from = strtotime('January 1st');
		$vfrateGroups = Billrun_Factory::config()->getConfigValue('vfdays.fraud.groups.vodafone',['VF','IRP_VF_10_DAYS']);
//		$elements[] = array(
//			'$match' => array(
//				'$or' => array(
//					array('subscriber_id' => 531464),
//					array('sid' => 531464),
//				),
//			)
//		);
		
		$elements[] = array(
			'$match' => array(
//				'unified_record_time' => array(
//					'$gte' => new MongoDate($from),
//				),
				'record_opening_time' => array(
					'$gte' => date('YmdHis', $from),
				),
				'arategroup' => [ '$in' => $vfrateGroups],
			),
		);
		if ($max_datetime) {
			$elements[count($elements)-1]['$match']['record_opening_time']['$lte'] = date('YmdHis', strtotime($max_datetime));
		}
		$elements[] = array(
			'$group' => array(
				'_id' => array(
					'day_key' => array('$substr' =>
						array(
							'$record_opening_time',
							4,
							4
						)
					),
					'sid' => '$sid'
				),
			),
		);
		$elements[] = array(
			'$sort' => array(
				'_id.sid' => 1,
				'_id.day_key' => 1,
			),
		);
		$elements[] = array(
			'$group' => array(
				'_id' => '$_id.sid',
				'days_num' => array(
					'$sum' => 1,
				),
				'last_day' => array(
					'$last' => '$_id.day_key',
				)
			),
		);
		$elements[] = array(
			'$match' => array(
				'days_num' => array(
					'$gte' => $min_days,
				)
			)
		);
		$elements[] = array(
			'$project' => array(
				'_id' => 0,
				'sid' => '$_id',
				'count_days' => '$days_num',
				'last_date' => '$last_day',
				'min_days' => array(
					'$literal' => $min_days,
				),
				'max_days' => 45,
			)
		);
		
		MongoCursor::$timeout = -1;
		$res = Billrun_Factory::db()->linesCollection()->getMongoCollection()->aggregateCursor($elements, array('allowDiskUse' => TRUE, 'maxTimeMS' => 30*60*1000))->setReadPreference(MongoClient::RP_SECONDARY_PREFERRED)->timeout(-1);
		$it = iterator_to_array($res);
		return $it;
	}

}
