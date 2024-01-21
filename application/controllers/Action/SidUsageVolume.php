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
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests

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

		Billrun_Factory::log()->log("Request params Received: sids-" . $request['sids'] . ", from_date-" . $request['from_date'] . ", to_date-" . $request['to_date'], Zend_Log::INFO);
        $from = $request['from_date'];
        $to = $request['to_date'];
		$sids = array_unique(Billrun_Util::verify_array(explode(',', $request['sids']), 'int'));
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
        $collection = $linesCollection = Billrun_Factory::db()->linesCollection();
        $match = array(
			'$match' => array(
				'sid' => $sid,
				'urt' => array(
                    '$gte' => new MongoDate($from),
                    '$lte' => new MongoDate($to),
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
		Billrun_Factory::log("Query: " . json_encode($match));
		Billrun_Factory::log("Query: " . json_encode($group));
        $collection->aggregateWithOptions([$match, $project, $addFields, $group, $project3, $match2], ['allowDiskUse' => true]);
    }

	protected function getSumQuery($cond, $divide, $cond_second_param = null ,$use_divide = true) {
		return ['sum' => [
				array ('cond' => $cond),
				$use_divide ? array ('divide' => $divide) : $cond_second_param,
				0
			  ]];
	}

	protected function getCallsInMinutesQuery() {
		$cond = ['and' => array(
			['eq' => ['type', 'nsn']],
			['eq' => ['usaget', 'call']],
			['ne' => ['in_circuit_group_name', 'VOLT']],
			['ne' => ['out_circuit_group', '2120']]
		)];
		$divide = ['$usagev', 60];
		return $this->getSumQuery($cond, $divide);
	}

	protected function getDataInMbQuery() {
		$cond = ['$and' => array(
			['$type' => 'ggsn'],
			['$usaget' => 'call']
		)];
		$divide = ['$usagev', 1048576];
		return $this->getSumQuery($cond, $divide);
	}

	protected function getInterCallsInMinutesQuery() {
		$cond = ['$and' => array(
			['$type' => 'nsn'],
			['$usaget' => 'call'],
			['$ne' => ['$out_circuit_group','2120']]
		)];
		$divide = ['$usagev', 60];
		return $this->getSumQuery($cond, $divide);
	}

	protected function getSmsQuery() {
		$cond = ['$and' => array(
			['$type' => 'smsc'],
			['$usaget' => 'sms'],
			['$ne' => ['$roaming',true]]
		)];
		return $this->getSumQuery($cond, null, 1, false);
	}

	protected function getMmsQuery() {
		$cond = ['$and' => array(
			['$type' => 'mmsc'],
			['$usaget' => 'mms'],
			['$ne' => ['$out_circuit_group','2120']]
		)];
		$divide = ['$usagev', 60];
		return $this->getSumQuery($cond, $divide);
	}

	protected function getDataRoamingQuery() {
		$cond = ['$and' => array(
			['$type' => 'tap3'],
			['$usaget' => 'data']
		)];
		$divide = ['$usagev', 1048576];
		return $this->getSumQuery($cond, $divide);
	}

	protected function getCallsRoamingQuery() {
		$cond = ['$and' => array(
			['$usaget' => 'call'],
			['$or' => array(
				array('$type' => 'tap3'),
				array('$roaming' => true)
			)]
		)];
		$divide = ['$usagev', 60];
		return $this->getSumQuery($cond, $divide);
	}

	protected function getIncomingCallsRoamingQuery() {
		$cond = ['$and' => array(
			['$usaget' => 'incoming_call'],
			['$or' => array(
				array('$type' => 'tap3'),
				array('$roaming' => true)
			)]
		)];
		$divide = ['$usagev', 60];
		return $this->getSumQuery($cond, $divide);
	}

	protected function getSmsRoamingQuery() {
		$cond = ['$and' => array(
			['$type' => 'smsc'],
			['$roaming' => true]
		)];
		return $this->getSumQuery($cond, null, 1, false);
	}

}
