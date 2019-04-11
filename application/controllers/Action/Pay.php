<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Collect.php';

/**
 * Pay action class
 *
 * @package  Action
 * @since    0.5
 */
class PayAction extends ApiAction {
	use Billrun_Traits_Api_UserPermissions;
	
	public function execute() {
		$this->allowed();
		$request = $this->getRequest();
		Billrun_Factory::log()->log('Pay API call with params: ' . print_r($request->getRequest(), 1), Zend_Log::INFO);
		$method = $request->get('method');
		$action = $request->get('action');
		$txIdArray = json_decode($request->get('txid'), TRUE);
		$unfreezedDeposits = array();
		$deposits = array();
		$jsonPayments = $request->get('payments');
		if (!$method) {
			return $this->setError('No method found', $request->getPost());
		}
		if (empty($action) && !(($paymentsArr = json_decode($jsonPayments, TRUE)) && (json_last_error() == JSON_ERROR_NONE) && is_array($paymentsArr))) {
			return $this->setError('No payments found', $request->getPost());
		}
		try {
			foreach ($paymentsArr as $key => $inputPayment) {
				if (!isset($inputPayment['deposit'])) {
					continue;
				}
				if ($inputPayment['deposit'] != true) {
					throw new Exception('deposit parameter can only be set to true');
				}
				$className = Billrun_Bill_Payment::getClassByPaymentMethod($method);
				$deposit = new $className($inputPayment);
				$deposits[] = $deposit;
				$deposit->save();
				unset($paymentsArr[$key]);
			}
			if (!empty($deposits) && empty($paymentsArr)) {
				$this->getController()->setOutput(array(array(
					'status' => 1,
					'desc' => 'success',
					'input' => $request->getPost(),
					'details' => array(
						'deposits_saved' => count($deposits),
					),
				)));
				return;
			}
			if ($this->useDeposits($action, $txIdArray)) {
				foreach ($txIdArray as $txid) {
					$deposit = Billrun_Bill_Payment::getInstanceByid($txid);
					if (empty($deposit)) {
						continue;
					}
					$depositUnfreezed = $deposit->unfreezeDeposit();
					if ($depositUnfreezed) {
						$unfreezedDeposits[] = $txid;
					}
				}
				$this->getController()->setOutput(array(array(
					'status' => 1,
					'desc' => 'success',
					'input' => $request->getPost(),
					'details' => array(
						'deposits_received' => $txIdArray,
						'deposits_unfreezed' => $unfreezedDeposits,
					),
				)));
			
				return;
			}
			$payments = Billrun_Bill::pay($method, $paymentsArr);
			$emailsToSend = array();
			foreach ($payments as $payment) {
				$method = $payment->getPaymentMethod();
				if (in_array($method, array('wire_transfer', 'cheque')) && $payment->getDir() == 'tc') {
					if (!isset($emailsToSend[$method])) {
						$emailsToSend[$method] = array(
							'operation' => $method . '_refund',
							'entities' => array(),
						);
					}
					$entity = array(
						'aid' => $payment->getAccountNo(),
						'amount' => $payment->getAmount(),
						'BIC' => $payment->getBIC(),
						'IBAN' => $payment->getIBAN(),
						'bank_name' => $payment->getBankName(),
						'date' => date(Billrun_Base::base_datetimeformat, $payment->getTime()->sec),
					);
					$emailsToSend[$method]['entities'][] = $entity;
				}
			}
			if ($emailsToSend) {
				$subscriber = Billrun_Factory::subscriber();
				foreach ($emailsToSend as $method => $data) {
					$emailsResult = $subscriber->sendBillingOperationsNotifications($data);
					if (isset($emailsResult['status']) && $emailsResult['status'] == 1) {
						Billrun_Factory::log()->log($method . ' refund: ' . $emailsResult['emails_sent'] . ' emails queued for sending.', Zend_Log::INFO);
					} else {
						Billrun_Factory::log()->log('CRM returned with error when trying to send emails (' . $method . ' refund)', Zend_Log::ALERT);
					}
				}
			}
			$this->getController()->setOutput(array(array(
					'status' => 1,
					'desc' => 'success',
					'input' => $request->getPost(),
					'details' => array(
						'payments_received' => count($paymentsArr),
						'payments_saved' => count($payments),
					),
			)));
		} catch (Exception $ex) {
			$this->setError($ex->getMessage(), $request->getPost());
			return;
		}
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_WRITE;
	}
	
	/**
	 * Check if need to unfreeze deposits or not.
	 * @param string $action - action to execute.
	 * @param array $txIdArray - array of tx id.
	 * 
	 * @return true if need to unfreeze deposits
	 */
	protected function useDeposits($action, $txIdArray) {
		return !empty($action) && $action == 'use_deposit' && !empty($txIdArray);
	}
}
