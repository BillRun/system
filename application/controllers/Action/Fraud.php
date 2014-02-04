<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Fraud action class
 *
 * @package  Action
 * @since    0.5
 */
class FraudAction extends Action_Base {

	/**
	 * method that outputs accounts, subscribers and cost of the fraud subscribers
	 * it's called automatically by the api main controller
	 */
	public function execute() {
		Billrun_Factory::log()->log("Execute Fraud", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests
		
		$params = array('filterBy' => array('usagev' => array('usaget')), 'thershold');

		if (($param = $this->isParamMissing($params, $request)) != FALSE) {
				$msg = 'Missing required parameter: ' . $param;
				Billrun_Factory::log()->log($msg, Zend_Log::ERR);
				$this->getController()->setOutput(array(array(
						'status' => 0,
						'desc' => 'failed',
						'output' => $msg,
				)));
				return;
		}

		
		Billrun_Factory::log()->log("Request params Received: filterBy-" . $request['filterBy'] . ", thershold-" . $request['thershold'] . ", exclude-" .  Billrun_Util::getArrVal($request['exclude']) . 
									", usaget-" .  Billrun_Util::getArrVal($request['usaget']) . ", plan-" .  Billrun_Util::getArrVal($request['plan']) . ", page-" .  Billrun_Util::getArrVal($request['page']), Zend_Log::INFO);

		$balances = new BalancesModel(array( 'size' => Billrun_Factory::config()->getConfigValue('balances.accounts.limit', 50000),'page'=>  Billrun_Util::getArrVal($request['page'],1) ));
		
		$results = $balances->getFraudBalances($request['filterBy'], $request['thershold'], Billrun_Util::getArrVal($request['exclude']), Billrun_Util::getArrVal($request['usaget']), Billrun_Util::getArrVal($request['plan']));		
		
		if (empty($results)) {
			$msg = 'No result, received parameters: ' . print_r($request, true);
			Billrun_Factory::log()->log('No result, received parameters: ' . print_r($request, true), Zend_Log::ERR);
			$this->getController()->setOutput(array(array(
					'status' => 0,
					'desc' => 'failed',
					'output' => $msg,
			)));
			return;
		}
		
		$counter = 0;
		$accounts = array();		
		
		foreach ($results as $result) {

			$value = $result['sum'];
			$accounts['aid'][$result['_id']['aid']]['subs'][$result['_id']['sid']] = $value;
			$counter++;
		}

		$this->getController()->setOutput(array(array(
				'status' => 1,
				'desc' => 'success',
				'subscribers_count' => $counter,
				'filterBy' => $request['filterBy'],
				'usagev' => $request['thershold'],
				'usaget' => $request['usaget'],
				'plan' => $request['plan'],
				'page' => $request['page'],
				'output' => $accounts,
		)));

		return true;
	}

	protected function isParamMissing($params,$request) {
		foreach ($params as $key => $val) {
					$param = is_numeric($key) ? $val : $key;
					
					if (!isset($request[$param])) {						
						return $param;
					}
					if(is_array($val)) {
						return $this->isParamMissing($val[$request[$param]], $request);
					}
		}
		return FALSE;
	}
}
