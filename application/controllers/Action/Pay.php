<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
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
		$method = $request->getPost('method');
		$jsonPayments = $request->getPost('payments');

		if (!(($paymentsArr = json_decode($jsonPayments, TRUE)) && (json_last_error() == JSON_ERROR_NONE) && is_array($paymentsArr))) {
			return $this->setError('No payments found', $request->getPost());
		}
		try {
			$payments = static::pay($method, $paymentsArr);
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

	public static function pay($method, $paymentsArr, $options = array()) {
		$involvedAccounts = $payments = array();
		if (in_array($method, array('cheque', 'wire_transfer', 'cash', 'credit', 'write_off', 'debit'))) {
			$className = Billrun_Bill_Payment::getClassByPaymentMethod($method);
			foreach ($paymentsArr as $rawPayment) {
				$aid = intval($rawPayment['aid']);
				$dir = Billrun_Util::getFieldVal($rawPayment['dir'], null);
				if ($dir == 'fc' || is_null($dir)) { // attach invoices to payments and vice versa
					if (!empty($rawPayment['pays']['inv'])) {
						$paidInvoices = $rawPayment['pays']['inv']; // currently it is only possible to specifically pay invoices only and not payments
						$invoices = Billrun_Bill_Invoice::getInvoices(array('aid' => $aid, 'invoice_id' => array('$in' => Billrun_Util::verify_array(array_keys($paidInvoices), 'int'))));
						if (count($invoices) != count($paidInvoices)) {
							throw new Exception('Unknown invoices for account ' . $aid);
						}
						if (($rawPayment['amount'] - array_sum($paidInvoices)) <= -Billrun_Bill::precision) {
							throw new Exception($aid . ': Total to pay is less than the subtotals');
						}
						foreach ($invoices as $invoice) {
							$invoiceObj = Billrun_Bill_Invoice::getInstanceByData($invoice);
							if ($invoiceObj->isPaid()) {
								throw new Exception('Invoice ' . $invoiceObj->getId() . ' already paid');
							}
							if (!is_numeric($rawPayment['pays']['inv'][$invoiceObj->getId()])) {
								throw new Exception('Illegal amount ' . $rawPayment['pays']['inv'][$invoiceObj->getId()] . ' for invoice ' . $invoiceObj->getId());
							} else {
								$invoiceAmountToPay = floatval($paidInvoices[$invoiceObj->getId()]);
							}
							if ((($leftToPay = $invoiceObj->getLeftToPay()) < $invoiceAmountToPay) && (number_format($leftToPay, 2) != number_format($invoiceAmountToPay, 2))) {
								throw new Exception('Invoice ' . $invoiceObj->getId() . ' cannot be overpaid');
							}
							$updateBills['inv'][$invoiceObj->getId()] = $invoiceObj;
						}
					} else {
						$leftToSpare = floatval($rawPayment['amount']);
						$unpaidBills = Billrun_Bill::getUnpaidBills(array('aid' => $aid));
						foreach ($unpaidBills as $rawUnpaidBill) {
							$unpaidBill = Billrun_Bill::getInstanceByData($rawUnpaidBill);
							$invoiceAmountToPay = min($unpaidBill->getLeftToPay(), $leftToSpare);
							if ($invoiceAmountToPay) {
								$billType = $unpaidBill->getType();
								$billId = $unpaidBill->getId();
								$leftToSpare -= $rawPayment['pays'][$billType][$billId] = $invoiceAmountToPay;
								$updateBills[$billType][$billId] = $unpaidBill;
							}
						}
					}
				}
				$involvedAccounts[] = $aid;
				$payments[] = new $className($rawPayment);
			}
			$res = Billrun_Bill_Payment::savePayments($payments);
			if ($res && isset($res['ok']) && $res['ok']) {
				foreach ($payments as $payment) {
					if ($payment->getDir() == 'fc') {
						foreach ($payment->getPaidBills() as $billType => $bills) {
							foreach ($bills as $billId => $amountPaid) {
								$updateBills[$billType][$billId]->attachPayingBill($payment->getType(), $payment->getId(), $amountPaid)->save();
						}
					}
					} else {
						Billrun_Bill::payUnpaidBillsByOverPayingBills($payment->getAccountNo());
					}
				}
				if (!isset($options['collect']) || $options['collect']) {
					$involvedAccounts = array_unique($involvedAccounts);
//					CollectAction::collect($involvedAccounts);
				}
			} else {
				throw new Exception('Error encountered while saving the payments');
			}
		} else {
			throw new Exception('Unknown payment method');
		}
		return $payments;
	}

	protected function getPermissionLevel() {
		return PERMISSION_WRITE;
	}

}
