<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * AdjustPayments action class
 *
 * @package  Action
 * @since    5.0
 */
class AdjustPaymentsAction extends ApiAction {
	use Billrun_Traits_Api_UserPermissions;
	
	protected $payment_methods = array('cash', 'cheque', 'credit', 'wire_transfer', 'write_off');

	public function execute() {
		$this->allowed();
		$request = $this->getRequest();
		Billrun_Factory::log()->log('AdjustPayment API call with params: ' . print_r($request, 1), Zend_Log::INFO);
		try {
			$jsonAdjustments = $request->getPost('adjustments');
			if (($adjustmentsArr = json_decode($jsonAdjustments, TRUE)) && (json_last_error() == JSON_ERROR_NONE) && is_array($adjustmentsArr)) {
				$errorPayments = array();
				$newPayments = $payments = array();
				foreach ($adjustmentsArr as $rawAdjustment) {
					if (!empty($rawAdjustment['id'])) {
						if (isset($rawAdjustment['method']) || isset($rawAdjustment['amount'])) {
							if (isset($adjustments[$rawAdjustment['id']])) {
								return $this->setError('Duplicate id ' . $rawAdjustment['id'], $request->getPost());
							}
							$payment = Billrun_Bill_Payment::getInstanceByid($rawAdjustment['id']);
							if ($payment) {
								$method = $payment->getBillMethod();
								if (in_array($method, $this->payment_methods) && !($payment->isRejection() || $payment->isRejected() || $payment->isCancellation() || $payment->isCancelled() 
									|| $payment->isDeniedPayment() || $payment->isDenial() || $payment ->isWaiting())) {
									if (isset($rawAdjustment['method'])) {
										if (in_array($rawAdjustment['method'], $this->payment_methods)) {
											if ($rawAdjustment['method'] != $method) {
												$adjustments[$rawAdjustment['id']]['method'] = $rawAdjustment['method'];
											} else {
												$errorPayments['unsaved_adjustments'][] = $rawAdjustment['id'];
												continue;
											}
										} else {
											return $this->setError('Illegal payment method ' . $rawAdjustment['method'], $request->getPost());
										}
									}
									if (isset($rawAdjustment['amount'])) {
										if ($payment->getAmount() != $rawAdjustment['amount']) {
											$adjustments[$rawAdjustment['id']]['amount'] = $rawAdjustment['amount'];
										} else {
											$errorPayments['unsaved_adjustments'][] = $rawAdjustment['id'];
											continue;
										}
									}
									$id = $rawAdjustment['id'];
									unset($rawAdjustment['id'], $rawAdjustment['amount'], $rawAdjustment['method']);
									$adjustments[$id]['extra_data'] = $rawAdjustment;
									$payments[] = $payment;
								} else {
									return $this->setError('Payment ' . $rawAdjustment['id'] . ' not applicable', $request->getPost());
								}
							} else {
								return $this->setError('Unknown id', $request->getPost());
							}
						} else {
							return $this->setError('Missing payment method or amount for payment ' . $rawAdjustment['id'], $request->getPost());
						}
					} else {
						return $this->setError('Missing id', $request->getPost());
					}
				}
				foreach ($payments as $payment) {
					$id = $payment->getId();
					$adjustment = $adjustments[$id];
					$newPayments[] = $payment->getCancellationPayment();
					$rawData = $payment->getRawData();
					if (isset($adjustment['method'])) {
						$rawData['method'] = $adjustment['method'];
					}
					if (isset($adjustment['amount'])) {
						if ($adjustment['amount']) {
							$rawData['amount'] = $adjustment['amount'];
						}
						else { // 0 means cancellation only
							continue;
						}
					}
					unset($rawData['_id'], $rawData['pays'], $rawData['due']);
					$rawData['deposit_slip'] = isset($rawData['deposit_slip'])? $rawData['deposit_slip'] : '';
					$rawData['source'] = isset($rawData['source'])? $rawData['source'] : 'web';
					$rawData['deposit_slip_bank'] = isset($rawData['deposit_slip_bank'])? $rawData['deposit_slip_bank'] : '';
					$rawData['correction'] = true;
					$className = Billrun_Bill_Payment::getClassByPaymentMethod($rawData['method']);
					$newPayments[] = new $className(array_merge($adjustment['extra_data'],$rawData));
				}
				foreach ($newPayments as $newPayment) {
					$newPayment->setConfirmationStatus(false);
				}
				
				if ($newPayments) {
					Billrun_Bill_Payment::savePayments($newPayments);
				}
				foreach ($payments as $payment) {
					$id = $payment->getId();
					$payment->markCancelled()->save();
					$payment->detachPaidBills();
					Billrun_Bill::payUnpaidBillsByOverPayingBills($payment->getAccountNo());
					Billrun_Factory::dispatcher()->trigger('afterPaymentAdjusted', array($payment->getAmount(), $adjustments[$id]['amount'], $payment->getAccountNo()));
				}
				$this->getController()->setOutput(array(array(
						'status' => 1,
						'desc' => 'success',
						'input' => $request->getPost(),
						'details' => array(
							'errors' => $errorPayments,
						),
				)));
			} else {
				return $this->setError('No adjustments found', $request->getPost());
			}
		} catch (Exception $ex) {
			return $this->setError($ex->getMessage(), $request->getPost());
		}
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_WRITE;
	}

}
