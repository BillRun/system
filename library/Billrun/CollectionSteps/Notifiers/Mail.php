<?php

/**
 * 
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Collect Mail Notifier
 *
 * @package  Billing
 * @since    5.0
 */
class Billrun_CollectionSteps_Notifiers_Mail extends Billrun_CollectionSteps_Notifiers_Abstract {

	protected function run() {
		$aid = $this->getAid();
		$collection = Billrun_Bill::getTotalDueForAccount($aid, false);
		$account = $this->getAccount($aid);
		$params = array(
			'account' => $account,
			'collection' => $collection,
		);
		$body = $this->getBody($params);
		$subject = $this->getSubject($params);
		return Billrun_Util::sendMail($subject, $body, $account->email, array(), true);
	}
	
	/**
	 * parse the response received after run
	 * @return mixed
	 */
	protected function parseResponse($response) {
		return !empty($response);
	}
	
	protected function getBody($params) {
		$body = $this->task['step_config']['body'];
		return $this->updateDynamicData($body, $params);
	}
	
	protected function getSubject($params) {
		$subject = $this->task['step_config']['subject'];
		return $this->updateDynamicData($subject, $params);
	}

}
