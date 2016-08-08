<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Datausage action class
 *
 * @package  Action
 * @since    0.5
 */
class DatausageAction extends Action_Base {

	/**
	 * method that outputs account, subscribers and usage of requested accounts and requested date usage
	 * it's called automatically by the api main controller
	 */
	public function execute() {
		Billrun_Factory::log("Execute data triggers", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests

		$params = array('plan', 'data_usage', 'from_account_id', 'to_account_id', 'billrun');
		foreach ($params as $param) {
			if (!isset($request[$param])) {
				$msg = 'Missing required parameter: ' . $param;
				Billrun_Factory::log($msg, Zend_Log::ERR);
				$this->getController()->setOutput(array(array(
						'status' => 0,
						'desc' => 'failed',
						'output' => $msg,
				)));
				return;
			}
		}

		Billrun_Factory::log("Request params Received: plan-" . $request['plan'] . ", data_usage-" . $request['data_usage'] . ", from_account_id-" . $request['from_account_id'] . ", to_account_id-" . $request['to_account_id'] . ", billrun-" . $request['billrun'], Zend_Log::INFO);

		$balances = new BalancesModel(array('size' => Billrun_Factory::config()->getConfigValue('balances.accounts.limit', 50000)));
		$results = $balances->getBalancesVolume($request['plan'], $request['data_usage'], $request['from_account_id'], $request['to_account_id'], $request['billrun']);
		if (empty($results)) {
			Billrun_Factory::log('Some error happen, no result, received parameters: ' . print_r($request, true), Zend_Log::ERR);
			return;
		}

		$counter = 0;
		$accounts = array();
		foreach ($results as $result) {
			$accounts['aid'][$result['aid']]['subs'][$result['sid']] = Billrun_Util::byteFormat($result['balance']['totals']['data']['usagev'], 'MB', 2, false, '.', '');
			$counter++;
		}

		$this->getController()->setOutput(array(array(
				'status' => 1,
				'desc' => 'success',
				'subscribers_count' => $counter,
				'output' => $accounts,
		)));

		return true;
	}

}
