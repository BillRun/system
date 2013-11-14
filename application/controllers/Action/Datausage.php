<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
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
		Billrun_Factory::log()->log("Execute refund", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests

		if (empty($request['gift']) || empty($request['data_usage']) || empty($request['from_account_id']) || empty($request['to_account_id']) || empty($request['billrun'])) {
			Billrun_Factory::log()->log('There are missing required parameters: ' . print_r($request, true), Zend_Log::ERR);
			return;
		}

		$balances = new BalancesModel();
		$results = $balances->getBalancesVolume($request['gift'], $request['data_usage'], $request['from_account_id'], $request['to_account_id'], $request['billrun']);
		if (empty($results)) {
			Billrun_Factory::log()->log('Some error happen, no result, received parameters: ' . print_r($request, true), Zend_Log::ERR);
			return;
		}

		$counter = 0;
		foreach ($results as $result) {
			$accounts['aid'][$result['aid']]['sid'][$result['sid']] = Billrun_Util::byteFormat($result['balance']['totals']['data']['usagev'], 'MB');
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
