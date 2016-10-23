<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing paypage controller class
 *
 * @package  Controller
 * @since    5.0
 */
class ExternalPaypageController extends Yaf_Controller_Abstract {
	public function init() {
		Billrun_Factory::db();
	}

	public function indexAction() {
		$view = new Yaf_View_Simple(Billrun_Factory::config()->getConfigValue('application.directory') . '/views/paypage');
		$request = $this->getRequest()->getRequest();
		$query = array(
			'type' => 'account',
			'aid' => intval($request['aid'])
		);
		$query = array_merge($query, Billrun_Utils_Mongo::getDateBoundQuery());
		$selectedPlan = $request['plan'];
		$account = Billrun_Factory::db()->subscribersCollection()->query($query)->cursor()->current()->getRawData();
		$config = Billrun_Factory::db()->configCollection()->query()->cursor()->sort(array('_id' => -1))->current()->getRawData();
		$plans = Billrun_Factory::db()->plansCollection()->query(Billrun_Utils_Mongo::getDateBoundQuery())->cursor();
		$planNames = array();
		foreach ($plans as $plan) {
			$p = $plan->getRawData();
			$planNames[] = $p['name'];
		}
		$this->getView()->assign('account', $account);
	        $this->getView()->assign('config', $config['subscribers']['account']['fields']);
	        $this->getView()->assign('payment_gateways', $config['payment_gateways']);
		$this->getView()->assign('plans', $planNames);
		$this->getView()->assign('plan', $selectedPlan);
		return $view->render();
	}

	public function createAction() {
		$request = $this->getRequest()->getRequest();
		$create = new Billrun_ActionManagers_Subscribers_Create();
		$type = empty($request['aid']) ? 'account' : 'subscriber';
		if (empty($request['aid'])) {
			unset($request['aid']);
		} else {
			$request['aid'] = intval($request['aid']);
		}
		$query = array(
			"type" => $type,
			"subscriber" => json_encode($request)
		);
		$jsonObject = new Billrun_AnObj($query);
		if (!$create->parse($jsonObject)) {
			/* TODO: HANDLE ERROR! */
			return false;
		}
		if (!($res = $create->execute())) {
			/* TODO: HANDLE ERROR! */
			return false;
		}
		$secret = Billrun_Factory::config()->getConfigValue("shared_secret.key");
		$data = array(
			"aid" => $res['details']['aid'],
			"t" => time()
		);
		$hashResult = hash_hmac("sha512", json_encode($data), $secret);
		$sendData = array(
			"data" => $data,
			"signature" => $hashResult
		);

		header("Location: /creditguard/creditguard?signature=".$hashResult."&data=".json_encode($data));
		return false;
	}

}
