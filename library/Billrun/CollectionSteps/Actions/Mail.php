<?php

/**
 * 
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Collect Mail Action
 *
 * @package  Billing
 * @since    5.0
 */
class Billrun_CollectionSteps_Actions_Mail implements Billrun_CollectionSteps_TaskStrategy {

	public function run($task) {
		$collection = Billrun_Bill::getTotalDueForAccount($task['aid']);
		$account = $this->getAccount($task['aid']);
		$params = array(
			'account' => $account,
			'collection' => $collection,
		);
		$body = $this->updateDynamicData($task['content']['body'], $params);
		$subject = $this->updateDynamicData($task['content']['subject'], $params);
		$res = Billrun_Util::sendMail($subject, $body, $account->email, array(), true);
		return !empty($res);
	}

	protected function getAccount($aid) {
		$billrunAaccount = new Billrun_Account_Db();
		$billrunAaccount->load(array('aid' => $aid));
		return $billrunAaccount;
	}

	protected function updateDynamicData($string, $params) {
		$replaced_string = Billrun_Factory::templateTokens()->replaceTokens($string, $params);
		return $replaced_string;
	}
}
