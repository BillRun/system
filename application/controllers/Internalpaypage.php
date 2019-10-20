<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

require_once APPLICATION_PATH . '/application/controllers/Externalpaypage.php';

/**
 * Billing paypage controller class
 *
 * @package  Controller
 * @since    5.0
 */

class InternalPaypageController extends ExternalPaypageController {
	use Billrun_Traits_Api_UserPermissions;
	use Billrun_Traits_Api_PageRedirect;

	public function init() {
		Billrun_Factory::db();
	}

	public function createAction() {
		$this->allowed();
		$request = $this->getRequest()->getRequest();
		$type = empty($request['aid']) ? 'account' : 'subscriber';
		if (empty($request['aid'])) {
			unset($request['aid']);
		} else {
			$request['aid'] = intval($request['aid']);
		}

		if ($request['action'] !== 'updatePaymentGateway') {
			Billrun_Factory::log("Creating new payment gateway " . $request['payment_gateway'],  Zend_Log::INFO);
			$create = new Billrun_ActionManagers_Subscribers_Create();
			if (isset($request['services']) && is_array($request['services'])) {
				$request['services'] =  array_map(function($srv) { return array('name'=>$srv); }, $request['services']);
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
		
			//payment_gateway already exist, redirect to return url
			if (empty($request['payment_gateway'])) {
				header("Location: " . $request['return_url']);
				return false;
			}
		} else {
			$index = 0;
			$account = Billrun_Factory::account();
			$account->loadAccount(array('aid' => $request['aid']));
			$accountPg = $account->payment_gateway;		
			$prevPgName = isset($accountPg['active']['name']) ? $accountPg['active']['name'] : $request['payment_gateway'];
			if ($prevPgName != $request['payment_gateway']) {
				Billrun_Factory::log("Changing payment gateway from " . $prevPgName . ' to ' . $request['payment_gateway'] . " for account: " . $request['aid'], Zend_Log::INFO);
			} else {
				Billrun_Factory::log("Creating payment gateway " . $request['payment_gateway'], Zend_Log::INFO);
			}
			$prevPaymentGateway = Billrun_PaymentGateway::getInstance($prevPgName);			
			if ($prevPaymentGateway->isUpdatePgChangesNeeded()) {
				$PrevPgParams = array();
				if (!isset($accountPg['former'])) { 
					$previousPg = array();
				} else {
					$previousPg = $accountPg['former'];
					$counter = 0;
					foreach ($previousPg as $gateway) {
						if ($gateway['name'] == $prevPgName) {
							$PrevPgParams = $previousPg[$counter];
							unset($previousPg[$counter]);
							$index = $counter;
						} 
						$counter++;
					}
					
				}
				$pgAccountDetails = !empty($accountPg['active']['name']) ? $prevPaymentGateway->getNeededParamsAccountUpdate($accountPg['active']) : $prevPaymentGateway->getNeededParamsAccountUpdate($PrevPgParams['params']);		
				$pgParams = array('name' => $prevPgName, 'pgAccountDetails' => $pgAccountDetails);
				$currentPg = array(
					'name' => $pgParams['name'],
					'params' => $pgParams['pgAccountDetails']
				);
				$previousPg[$index] = $currentPg;
				if ($prevPaymentGateway->needUpdateFormerGateway($pgAccountDetails)) {
					$subscribersColl = Billrun_Factory::db()->subscribersCollection();
					$accountQuery = Billrun_Utils_Mongo::getDateBoundQuery();
					$accountQuery['type'] = 'account';
					$accountQuery['aid'] = $request['aid'];
					$subscribersColl->update($accountQuery, array('$set' => array('payment_gateway.former' => $previousPg)));
				}
				$prevPaymentGateway->deleteAccountInPg($pgAccountDetails);
			}
		}
		
		$secret = Billrun_Utils_Security::getValidSharedKey();
		$data = array(
			"aid" => $request['aid'],
			"name" => $request['payment_gateway'],
			"type" => $type,
			"return_url" => urlencode($request['return_url']),
		);
		$signed = Billrun_Utils_Security::addSignature($data, $secret['key']);

		header("Location: /paymentgateways/getRequest?data=" . json_encode($signed));
		return false;
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_ADMIN;
	}
}
