<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Collect.php';

/**
 * Cancel payments action class
 *
 * @package  Action
 * @since    5.9
 */
class CancelPaymentsAction extends ApiAction {

	use Billrun_Traits_Api_UserPermissions;

	public function execute() {
		$this->allowed();
		$request = $this->getRequest();
		Billrun_Factory::log()->log('Cancellations API call with params: ' . print_r($request->getRequest(), 1), Zend_Log::INFO);
		$cancellations = $request->get('cancellations');
		if (!(($cancellationsArr = json_decode($cancellations, TRUE)) && (json_last_error() == JSON_ERROR_NONE) && is_array($cancellationsArr))) {
			return $this->setError('No cancellations found', $request->getPost());
		}
		$skipErrors = !empty($request->get('skip_errors')) ? $request->get('skip_errors') : false;
		$ufPerTxid = array();

		try {
			$paymentsToCancel = $this->verifyPaymentsCanBeCancelled($cancellationsArr, $ufPerTxid);
			if (!$skipErrors && !empty($paymentsToCancel['errors'])) {
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
				$payment->addUfFields($currentUf);
				$cancellationPayment = $payment->getCancellationPayment();
				$cancellationPayments[] = $cancellationPayment;
			}
			foreach ($cancellationPayments as $cancellation) {
				$cancellation->setConfirmationStatus(false);
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

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_WRITE;
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
					if (isset($cancellation['amount']) && ($cancellation['amount'] != $matchedPayment->getAmount())) {
						$errors[] = "Cancellation amount not matching payment amount for $txid";
						$matched = false;
					}
					if (isset($cancellation['uf'])) {
						$ufPerTxid[$cancellation['txid']] = $cancellation['uf'];
					}
					if ($matchedPayment->isCancellation() || $matchedPayment->isCancelled() || $matchedPayment->isRejected() || $matchedPayment->isRejection()) {
						$errors[] = "$txid cannot be cancelled";
						$matched = false;
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
