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
		$action = !is_null($request->get('action')) ? $request->get('action') : '';
		$txIdArray = json_decode($request->get('txid'), TRUE);
		$deposits = array();
		$jsonPayments = $request->get('payments');
		if (!$method && !in_array($action, array('cancel_payments', 'use_deposit'))) {
			return $this->setError('No method found', $request->getPost());
		}
		if (empty($action) && !(($paymentsArr = json_decode($jsonPayments, TRUE)) && (json_last_error() == JSON_ERROR_NONE) && is_array($paymentsArr))) {
			return $this->setError('No payments found', $request->getPost());
		}
		try {
			switch ($action) {
				case 'split_bill':
					$this->executeSplitBill($request);
					return;
				case 'use_deposit':
					$this->unfreezeDeposits($txIdArray, $request);
					return;
				case 'cancel_payments': 
					$this->cancelPayments($request);
					return;
				default:
					break;
			}
			if ($method == 'installment_agreement') {
				throw new Exception("Method installment_agreement must be transferred with action split_bill");
			}
			foreach ($paymentsArr as $key => $inputPayment) {
				if (empty($inputPayment['deposit'])) {
					continue;
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
			$payResponse = Billrun_PaymentManager::getInstance()->pay($method, $paymentsArr);
			$payments = $payResponse['payment'];
			$emailsToSend = array();
			foreach ($payments as $payment) {
				$method = $payment->getBillMethod();
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
	 * unfreeze deposits.
	 * @param array $txIdArray - array of tx id.
	 * @param string $request - API request.
	 * 
	 */
	protected function unfreezeDeposits($txIdArray, $request) {
		$unfreezedDeposits = array();
		foreach ($txIdArray as $txid) {
			$deposit = Billrun_Bill_Payment::getInstanceByid($txid);
			if (empty($deposit)) {
				continue;
			}
			$depositUnfreezed = $deposit->unfreezeDeposit();
			$deposit->setBalanceEffectiveDate();
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
	}
		
	/**
	 * Creates rec with method installment_agreement and splits it to installments.
	 * @param array $params - parameters for split bill action.
	 * 
	 */
	protected function executeSplitBill($request) {
		$params['aid'] = !empty($request->get('aid')) ? intval($request->get('aid')) : '';
		$executeSplitBill = true;
		$params['amount'] = !empty($request->get('amount')) ? floatval($request->get('amount')) : 0;
		$params['installments_num'] = !empty($request->get('installments_num')) ?  $request->get('installments_num') : 0;
		$params['first_due_date'] = !empty($request->get('first_due_date')) ?  $request->get('first_due_date') : '';
		$installments = !empty($request->get('installments')) ?  $request->get('installments') : array();
		if(!empty($installments)) {
			$params['installments_agreement'] = json_decode($installments, true);
			$amountsArray = array_column($params['installments_agreement'], 'amount');
			if (!empty($amountsArray) && !Billrun_Util::isEqual(array_sum($amountsArray), $params['amount'], Billrun_Bill::precision)) {				
				throw new Exception('Sum of amounts in installments array must be equal to total amount');
			}
			$dueDateArray = array_column($params['installments_agreement'], 'due_date');
			if (count($dueDateArray) != count($params['installments_agreement'])) {
				throw new Exception('Due date field is mandatory for all installments');
			}
		}
		$note = $request->get('note');
		if (!empty($note)) {
			$params['note'] = $note;
		}
		if (empty($params['amount']) || empty($params['aid'])) {
			throw new Exception('In action split_bill must transfer amount and aid parameters');
		}
		if (!empty($params['installments_agreement']) && (!empty($params['installments_num']) || !empty($params['first_due_date']))) {
			throw new Exception('Passed parameters in contradiction');
		}
		if (empty($params['installments_num']) && !empty($params['first_due_date'])) {
			throw new Exception("Can't pass first_due_date withouh passing installments_num");
		}
		if (!empty($params['installments_num']) && $params['installments_num'] > $params['amount']) {
			throw new Exception("Number of installments can't be larger than the passed amount");
		}	
		if (!empty($params['installments_num']) && ($params['installments_num'] > $params['amount'])) {
			throw new Exception('Number of installments must be lower than passed amount');
		}
		$customerDebt = Billrun_Bill::getTotalDueForAccount($params['aid']);
		if ($params['amount'] > $customerDebt['without_waiting']) {
			throw new Exception("Passed amount is bigger than the customer debt");
		}
		if (!empty($request->get('first_charge_date'))) {
			$chargeNotBefore = strtotime($request->get('first_charge_date'));	
			$params['charge']['not_before'] = new MongoDate($chargeNotBefore);
		}
Billrun_Factory::dispatcher()->trigger('beforeSplitDebt', array($params, &$executeSplitBill));
		if (!$executeSplitBill) {
			throw new Exception("Failed executing split debt for aid: " . $params['aid']);
		}
		$ret = Billrun_Bill_Payment::createInstallmentAgreement($params);
		
		$this->getController()->setOutput(array(array(
			'status' => $ret['status'] ? 1 : 0,
			'desc' => $ret['status'] ? '' : 'failure',
			'input' => $request->getPost(),
			'details' => $ret['status'] ? 'created installments successfully . parameters: ' . json_encode($ret['payment_agreement'], true) : 'failed creating installments',
		)));

	}
	
	protected function cancelPayments($request) {
		Billrun_Factory::log()->log('Cancellations API call with params: ' . print_r($request->getRequest(), 1), Zend_Log::INFO);
		$cancellations = $request->get('cancellations');
		if (!(($cancellationsArr = json_decode($cancellations, TRUE)) && (json_last_error() == JSON_ERROR_NONE) && is_array($cancellationsArr))) {
			return $this->setError('No cancellations found', $request->getPost());
		}
		$ignoreErrors = !empty($request->get('ignore_errors')) ? $request->get('ignore_errors') : false;
		$ufPerTxid = array();

		try {
			$paymentsToCancel = $this->verifyPaymentsCanBeCancelled($cancellationsArr, $ufPerTxid);
			if (!$ignoreErrors && !empty($paymentsToCancel['errors'])) {
				$this->getController()->setOutput(array(array(
						'status' => 0,
						'desc' => 'error',
						'input' => $request->getPost(),
						'details' => array(
							'errors' => $paymentsToCancel['errors'],
						),
				)));
				return;
			}
			Billrun_Factory::dispatcher()->trigger('afterPaymentVerifiedToBeCancelled', $paymentsToCancel['payments']);
			$cancellationPayments = array();
			foreach ($paymentsToCancel['payments'] as $payment) {
				$id = $payment->getId();
				$currentUf = isset($ufPerTxid[$id]) ? $ufPerTxid[$id] : array();
				$payment->addUserFields($currentUf);
				$cancellationPayment = $payment->getCancellationPayment();
				$cancellationPayments[] = $cancellationPayment;
			}
			if ($cancellationPayments) {
				Billrun_Bill_Payment::savePayments($cancellationPayments);
			}
			$succeededCancels = array();
			foreach ($paymentsToCancel['payments'] as $payment) {
				array_push($succeededCancels, $payment->getId());
				$payment->markCancelled()->save();
				$payment->detachPaidBills();
				$payment->detachPayingBills();
				Billrun_Bill::payUnpaidBillsByOverPayingBills($payment->getAccountNo());
			}
		} catch (Exception $e) {
			return $this->setError($e->getMessage(), $request->getPost());
		}

		$this->getController()->setOutput(array(array(
				'status' => 1,
				'desc' => 'success',
				'input' => $request->getPost(),
				'details' => array(
					'succeeded_cancels' => $succeededCancels,
					'errors' => $paymentsToCancel['errors'],
				),
		)));
	}
	
	protected function verifyPaymentsCanBeCancelled($cancellations, &$ufPerTxid) {
		$payments = $errors = array();
		$missingTxidCounter = 0;

		foreach ($cancellations as $cancellation) {
			if (isset($cancellation['txid'])) {
				$txid = $cancellation['txid'];
				$matchedPayment = Billrun_Bill_Payment::getInstanceByid($cancellation['txid']);
				if (!empty($matchedPayment)) {
					$matched = true;
					if ($matchedPayment->isCancellation() || $matchedPayment->isCancelled() || $matchedPayment->isRejected() || $matchedPayment->isRejection()) {
						$errors[] = "$txid cannot be cancelled";
						$matched = false;
					} else if (isset($cancellation['amount']) && ($cancellation['amount'] != $matchedPayment->getAmount())) {
						$errors[] = "Cancellation amount not matching payment amount for $txid";
						$matched = false;
					}
					if (isset($cancellation['uf'])) {
						$ufPerTxid[$cancellation['txid']] = $cancellation['uf'];
					}
					if ($matched) {
						$payments[] = $matchedPayment;
					}
				} else {
					$errors[] = "$txid Not Found";
				}
			} else {
				$missingTxidCounter++;
			}
		}
		if ($missingTxidCounter > 0) {
			$errors[] = "$missingTxidCounter payments was transferred without txid";
		}
		return array('payments' => $payments, 'errors' => $errors);
	}
}
