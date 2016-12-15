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
		$subject = $task['content']['subject'];
		$account = $this->getAccount($task['aid']);
		$body = $this->updateBodyDynamicData($task, $account);
		$res = Billrun_Util::sendMail($subject, $body, $account->email, array(), true);
		return !empty($res);
	}

	protected function getAccount($aid) {
		$billrunAaccount = new Billrun_Account_Db();
		$billrunAaccount->load(array('aid' => $aid));
		return $billrunAaccount;
	}

	protected function updateBodyDynamicData($task, $account) {
		$body = $task['content']['body'];
		$translations = $this->getTranslations();
		foreach ($translations as $translation) {
			switch ($translation) {
				case "Customer Name":
					$replace = $account->firstname . " " . $account->lastname;
					$body = str_replace("[[$translation]]", $replace, $body);
					break;
				case "Debt":
					$balance = Billrun_Bill::getTotalDueForAccount($task['aid']);
					$replace = $balance['total'];
					$body = str_replace("[[$translation]]", $replace, $body);
					break;
			}
		}
		return $body;
	}

	protected function getTranslations() {
		return array(
			"Customer Name",
			"Debt",
		);
	}

}
