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
class Collect_Actions_Mail implements Collect_TaskStrategy {
	
	public function run($task){
		$subject = $task['content']['subject'];
		$account = $this->getAccount($task['aid']);
		$body = $this->updateBodyDynamicData($task, $account);
		$res = Billrun_Util::sendMail($subject, $body , $account['email'], array(), true);
		return !empty($res);
	}
	
	protected function getAccount($aid) {
		$query = array(
			'type' => 'account',
			'aid' => $aid,
			'to' => array('$gt' =>  new MongoDate()),
			'from' => array('$lt' => new MongoDate()),
		);
		
		$results = Billrun_Factory::db()->subscribersCollection()->query($query)->cursor()->limit(1)->current();
		if ($results->isEmpty()) {
			return false;
		}
		return $results->getRawData();
	}
	
	protected function updateBodyDynamicData($task, $account) {
		$body = $task['content']['body'];
		$translations = $this->getTranslations();
		
		foreach ($translations as $translation) {
			switch ($translation) {
				case "Customer Name":
					$replace = $account["firstname"] . " " . $account["lastname"];
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