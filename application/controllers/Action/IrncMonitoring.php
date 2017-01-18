<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * IRNC Usage Monitoring action class
 *
 * @package  Action
 */
class IrncMonitoringAction extends Action_Base {

	public function execute() {
		Billrun_Factory::log()->log("Execute IRNC Usage Monitoring API call", Zend_Log::INFO);
		$request = $this->getRequest();
		$alpha3 = $request->get("alpha3");
		$alpha3Array = json_decode($alpha3);
		if (empty($alpha3)) {
			$this->getController()->setOutput(array(array(
					'status' => 0,
					'desc' => 'need to supply alpha3',
					'input' => $request->getRequest(),
			)));
			return;
		}
		$result = $this->getSidPerAlpha3($alpha3Array);
		$this->getController()->setOutput(array(array(
				'status' => 1,
				'desc' => 'success',
				'input' => $request->getRequest(),
				'details' => $result,
		)));
		Billrun_Factory::log()->log("Done IRNC Usage Monitoring API call", Zend_Log::INFO);
	}

	protected function getSidPerAlpha3($alphaArray) {
		$currentDay = strtotime(date('Y-m-d', time()));
		$previousDay = strtotime(date('Y-m-d', strtotime('-1 day')));
		$dayStart = new MongoDate($previousDay);
		$dayEnd = new MongoDate($currentDay);
		
		$match = array(
			'$match' => array(
				'$or' => array(
					array('type' => 'ggsn'),
					array('type' => 'nrtrde'),
				),
				'unified_record_time' => array(
					'$gte' => $dayStart,
					'$lt' => $dayEnd
				),
				'alpha3' => array(
					'$in' => $alphaArray
				)
			),
		);

		$group = array(
			'$group' => array(
				'_id' => array(
					'sid' => '$sid',
					'alpha3' => '$alpha3'
				),
			)
		);
		$project = array(
			'$project' => array(
				'_id' => 0,
				'sid' => '$_id.sid',
				'alpha3' => '$_id.alpha3',
			)
		);

		$res = Billrun_Factory::db()->linesCollection()->aggregate($match, $group, $project);
		return $res;
	}
	
}