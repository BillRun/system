<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * ClearCall action class - find open (pre-paid) calls without available balance, 
 * and trigger clearCall event if necessary
 *
 * @package  Action
 * 
 * @since    4.0
 */
class ClearCallAction extends ApiAction {

	public function execute() {
		$this->_controller->addOutput("Running ClearCall API...");
		$additionalDataToLoad = array('calling_number', 'call_id', 'sid', 'aid', 'charging_type', 'usaget', 'time_date', 'time_zone', 'grantedReturnCode');
		$openCalls = self::getOpenCalls($additionalDataToLoad);
		foreach ($openCalls as $call) {
			$options = array(
				'sid' => $call['sid'] . '1',
				'aid' => $call['aid'],
				'urt' => $call['urt'],
				'charging_type' => $call['charging_type'],
				'usaget' => $call['usaget'],
			);
			$balance = Billrun_Factory::balance($options)->get('balance');
			if (is_null($balance) || !count($balance)) { // if the subscriber does not have available balance
				$row = $call;
				$row['call_reference'] = $row['_id'];
				unset($row['_id']);
				$response = (new Billrun_ActionManagers_Realtime_Call_ClearCallResponder($row))->getResponse();
				//TODO: send response
			}
		}
	}

	/**
	 * Finds all calls that have not yet terminated.
	 * 
	 * @param array $additionalDataToLoad Additional fields to load for call group
	 * @return type
	 */
	protected static function getOpenCalls($additionalDataToLoad = array()) {
		$urtRange = Billrun_Factory::config()->getConfigValue('cli.clearcall.urtRange');
		$startTime = date('Y-m-d H:i:s', strtotime($urtRange['start'],time()));
		$endTime = date('Y-m-d H:i:s', strtotime($urtRange['end'],time()));
		$query = array(
			array(
				'$group' => array(
					'_id' => '$call_reference',
					'calling_number' => array(
						'$first' => '$calling_number'
					),
					'statuses' => array(
						'$addToSet' => '$record_type'
					),
					'urt' => array(
						'$min' => '$urt'
					)
				)
			),
			array(
				'$match' => array(
					'statuses' => array(
						'$nin' => array('release_call')
					),
					'urt' => array(
						'$gte' => $startTime,
						'$lte' => $endTime,
					)
				)
			)
		);

		// Add additional data (assuming that it is the same for reference call group)
		foreach ($additionalDataToLoad as $field) {
			$query[0]['$group'][$field] = array(
				'$first' => '$' . $field
			);
		}

		return Billrun_Factory::db()->linesCollection()->aggregate($query);
	}

}
