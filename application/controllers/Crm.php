<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing crm controller class
 *
 * @package  Controller
 * @since    0.5
 */
class CrmController extends Yaf_Controller_Abstract {

	public function subscriberByDateBulkAction() {
		$inputArr = json_decode(file_get_contents('php://input'), TRUE);
		foreach ($inputArr as &$params) {
			$params = $this->getSubscriberDetails($params);
		}
		die(json_encode($inputArr));
	}

	public function subscriberByDateAction() {
		$result = $this->getSubscriberDetails($this->getRequest()->getRequest());
		die(json_encode($result));
	}

	protected function getSubscriberDetails($params) {
		$fieldName = isset($params['NDC_SN']) ? 'NDC_SN' : (isset($params['IMSI']) ? 'IMSI' : (isset($params['sid']) ? 'sid' : null));
		if (is_null($fieldName)) {
			return $params;
		}
		if ($fieldName == 'sid') {
			$params['subscriber_id'] = $params[$fieldName];
		} else {
			$params['subscriber_id'] = intval(substr($params[$fieldName], -6));
		}
		$params['account_id'] = intval(strrev($params['subscriber_id']));
		$randomNum = intval(substr($params[$fieldName], -2));
		$rowTime = new MongoDate(strtotime($params['DATETIME']));
		$plansQuery = array(
			'from' => array(
				'$lte' => $rowTime,
			),
			'to' => array(
				'$gte' => $rowTime,
			),
		);
		$plans = Billrun_Factory::db()->plansCollection()->query($plansQuery)->cursor()->sort(array('name' => 1));
		$planCount = $plans->count();
		$planIndex = floor($randomNum / 100 * $planCount);
		$plan = $plans->skip($planIndex)->current();
		$params['plan'] = $plan['name'];
		$params['google_play'] = array(
			'active' => ($randomNum > 10 ? 1 : 0),
			'address' => 'Some address ' . strval($params['account_id']),
			'password' => strval($params['account_id']) . strval($params['subscriber_id']),
		);
		return $params;
	}

}
