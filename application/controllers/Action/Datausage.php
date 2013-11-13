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

		$results = $this->getLines($request['gift'], $request['data_usage'], $request['from_account_id'], $request['to_account_id'], $request['billrun']);
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

		//print_r(array('status' => 1,'desc' => 'success','subscribers_count' => $counter,'output' => $accounts));
		
		return true;
	}

	/**
	 * method to receive the balances lines that over requested date usage
	 * 
	 * @return Mongodloid_Cursor Mongo cursor for iteration
	 */
	protected function getLines($gift, $data_usage, $from_account_id, $to_account_id, $billrun) {
		$params['name'] = $gift;
		$params['time'] = Billrun_Util::getStartTime($billrun);
		$plan_id = Billrun_Factory::plan($params);
		$id = $plan_id->get('_id')->getMongoID();
		
		$balances = Billrun_Factory::db()->balancesCollection();
		$data_usage_bytes = Billrun_Util::megabytesToBytesFormat($data_usage);
		
		return $balances->query(array(
					'balance.totals.data.usagev' => array('$gt' => $data_usage_bytes),
					'billrun_month' => $billrun,
					'current_plan'=> Billrun_Factory::db()->plansCollection()->createRef($id),
					'aid' => array('$gt' => (int)$from_account_id),
					'aid' => array('$lt' => (int)$to_account_id),
		));
	}

}
