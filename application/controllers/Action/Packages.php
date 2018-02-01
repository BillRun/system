<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2017 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Packages action class
 *
 * @package  Action
 * @since    0.5
 */
class PackagesAction extends ApiAction {

	public function execute() {
		$request = $this->getRequest();
		$sid = $request->get("sid");
		$packageId = $request->get("id");
		$billrunKey = $request->get("billrun");
		Billrun_Factory::log()->log("Execute packages api call to " . $sid . ' package ' . $packageId, Zend_Log::INFO);
		if (!is_numeric($sid)) {
			return $this->setError("sid is not numeric", $request);
		} else {
			settype($sid, 'int');
		}
		if (!is_numeric($packageId)) {
			return $this->setError("packageId is not numeric", $request);
		} else {
			settype($packageId, 'int');
		}
		$balancesColl = Billrun_Factory::db(array('name' => 'balances'))->balancesCollection()->setReadPreference('RP_PRIMARY');
		$query = array(
			'sid' => $sid, 
			'service_id' => $packageId
		);
		if (!is_null($billrunKey)) {
			if (Billrun_Util::isBillrunKey($billrunKey)) {
				$startTime = Billrun_Util::getStartTime($billrunKey);
				$endTime = Billrun_Util::getEndTime($billrunKey);
				$query['from'] = array('$gte' => new MongoDate($startTime));
				$query['to'] = array('$lte' => new MongoDate ($endTime));
			} else {
				return $this->setError("sid is not numeric", $request);
			}
		}
		$results = $balancesColl->query($query)->cursor();
		if (count($results) > 1) {
			return $this->setError("There is more than one matching package", $request);
		}
		$current = $results->current();
		if (!$current->isEmpty()) {
			$callsUsage = $current['balance']['totals']['call']['usagev'] + $current['balance']['totals']['incoming_call']['usagev'];
			$smsUsage = $current['balance']['totals']['sms']['usagev'];
			$dataUsage = $current['balance']['totals']['data']['usagev'];
			$packageUsage = array(
				'Call' => $callsUsage,
				'Sms' => $smsUsage,
				'Data' => $dataUsage
			);
		} else {
			$packageUsage = "There isn't a matching package";
		}
		
		$this->getController()->setOutput(array(array(
				'status' => 1,
				'desc' => 'success',
				'input' => $request,
				'details' => $packageUsage,
		)));
	}

}
