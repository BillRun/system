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
		$results = $balancesColl->query($query)->cursor()->current();
		if ($results->isEmpty()) {
			return $this->setError("There isn't a matching package", $request);
		}
		$callsUsage = $results['balance']['totals']['call']['usagev'] + $results['balance']['totals']['incoming_call']['usagev'];
		$smsUsage = $results['balance']['totals']['sms']['usagev'];
		$dataUsage = $results['balance']['totals']['data']['usagev'];
		$packageUsage = array(
			'Call' => $callsUsage,
			'Sms' => $smsUsage,
			'Data' => $dataUsage
		);
		
		$this->getController()->setOutput(array(array(
				'status' => 1,
				'desc' => 'success',
				'input' => $request,
				'details' => $packageUsage,
		)));
	}

}
