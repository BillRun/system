<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * SidUsageVolume action class
 *
 * @package  Action
 * @since    2.8
 */
class SidUsageVolumeAction extends Action_Base {

	/**
	 * method that outputs subscriber requested date usage
	 */
	public function execute() {
		Billrun_Factory::log()->log("Execute sid usage volume api", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest();
		if (!isset($request['sids'])) {
			$request = json_decode(file_get_contents('php://input'),true);
		}
		$params = array('sids', 'from_date', 'to_date');
		foreach ($params as $param) {
			if (!isset($request[$param])) {
				$msg = 'Missing required parameter: ' . $param;
				Billrun_Factory::log()->log($msg, Zend_Log::ERR);
				$this->getController()->setOutput(array(array(
						'status' => 0,
						'desc' => 'failed',
						'output' => $msg,
				)));
				return;
			}
		}

		Billrun_Factory::log()->log("Request params Received: sids-" . is_array($request['sids']) ? print_r($request['sids'], 1) : $request['sids'] . ", from_date-" . $request['from_date'] . ", to_date-" . $request['to_date'], Zend_Log::INFO);
        $from = $request['from_date'];
        $to = $request['to_date'];
		$sids = is_array($request['sids']) ? $request['sids'] : array_unique(json_decode($request['sids'], TRUE));
        foreach ($sids as $sid) {
            $results[$sid] = $this->getSidUsageVolume($sid, $from, $to);
        }
		if (empty($results)) {
			Billrun_Factory::log()->log('Some error happen, no result, received parameters: ' . print_r($request, true), Zend_Log::ERR);
			return;
		}

		$this->getController()->setOutput(array(array(
				'status' => 1,
				'desc' => 'success',
				'usage_volume' => $results
		)));

		return true;
	}

    public function getSidUsageVolume($sid, $from, $to) {
        $linesCollection = Billrun_Factory::db()->linesCollection();
        $match = array(
			'$match' => array(
				'sid' => $sid,
				'urt' => array(
                    '$gte' => new MongoDate(strtotime($from)),
                    '$lte' => new MongoDate(strtotime($to)),
                ),
                'billrun' => array('$exists' => true)
			)
		);
    	$group = array(
			'$group' => array(
				'_id' => '$sid',
				'data_in_mb' => $this->getDataInMbQuery(),
				'calls_in_minutes' => $this->getCallsInMinutesQuery(),
				'international_calls_in_minutes' => $this->getInterCallsInMinutesQuery(),
				'sms' => $this->getSmsQuery(),
				'mms' => $this->getMmsQuery(),
				'data_roaming' => $this->getDataRoamingQuery(),
				'calls_roaming' => $this->getCallsRoamingQuery(),
				'incoming_calls_roaming' => $this->getIncomingCallsRoamingQuery(),
				'sms_roaming' => $this->getSmsRoamingQuery()
			)
		);
		$ret = $linesCollection->aggregate([$match, $group], ["allowDiskUse" => true]);
		return current($ret);
    }

	protected function getSumQuery($cond, $divide, $cond_second_param = null ,$use_divide = true) {
		$res = array('$sum' => ['$cond' => [$cond]]);
		if ($use_divide) {
			$res['$sum']['$cond'][] = ['$divide' => $divide];
		} else {
			$res['$sum']['$cond'][] = $cond_second_param;
		}
		$res['$sum']['$cond'][] = 0;
		return $res;
	}

	protected function getCallsInMinutesQuery() {
		$cond = ['$and' => array(
			['$eq' => ['$type', 'nsn']],
			['$eq' => ['$usaget', 'call']],
			['$ne' => ['$in_circuit_group_name', 'VOLT']],
			['$ne' => ['$out_circuit_group', '2120']]
		)];
		$divide = ['$usagev', 60];
		return $this->getSumQuery($cond, $divide);
	}

	protected function getDataInMbQuery() {
		$cond = ['$and' => array(
			['$eq' => ['$type', 'ggsn']],
			['$eq' => ['$usaget', 'data']]
		)];
		$divide = ['$usagev', 1048576];
		return $this->getSumQuery($cond, $divide);
	}

	protected function getInterCallsInMinutesQuery() {
		$cond = ['$and' => array(
			['$eq' => ['$type', 'nsn']],
			['$eq' => ['$usaget', 'call']],
			['$eq' => ['$out_circuit_group','2120']]
		)];
		$divide = ['$usagev', 60];
		return $this->getSumQuery($cond, $divide);
	}

	protected function getSmsQuery() {
		$cond = ['$and' => array(
			['$eq' => ['$type', 'smsc']],
			['$eq' => ['$usaget', 'sms']],
			['$ne' => ['$roaming',true]]
		)];
		return $this->getSumQuery($cond, null, 1, false);
	}

	protected function getMmsQuery() {
		$cond = ['$and' => array(
			['$eq' => ['$type', 'mmsc']],
			['$eq' => ['$usaget', 'mms']],
			['$ne' => ['$out_circuit_group','2120']]
		)];
		return $this->getSumQuery($cond, null, 1, false);
	}

	protected function getDataRoamingQuery() {
		$cond = ['$and' => array(
			['$eq' => ['$type', 'tap3']],
			['$eq' => ['$usaget', 'data']]
		)];
		$divide = ['$usagev', 1048576];
		return $this->getSumQuery($cond, $divide);
	}

	protected function getCallsRoamingQuery() {
		$cond = ['$and' => array(
			['$eq' => ['$usaget', 'call']],
			['$or' => array(
				['$eq' => ['$type', 'tap3']],
				['$eq' => ['$roaming', true]]
			)]
		)];
		$divide = ['$usagev', 60];
		return $this->getSumQuery($cond, $divide);
	}

	protected function getIncomingCallsRoamingQuery() {
		$cond = ['$and' => array(
			['$eq' => ['$usaget', 'incoming_call']],
			['$or' => array(
				['$eq' => ['$type', 'tap3']],
				['$eq' => ['$roaming', true]]
			)]
		)];
		$divide = ['$usagev', 60];
		return $this->getSumQuery($cond, $divide);
	}

	protected function getSmsRoamingQuery() {
		$cond = ['$and' => array(
			['$eq' => ['$type', 'smsc']],
			['$eq' => ['$roaming', true]]
		)];
		return $this->getSumQuery($cond, null, 1, false);
	}

}
