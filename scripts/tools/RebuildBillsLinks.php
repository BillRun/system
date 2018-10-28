<?php


$dir = '/var/www/billrun/';
defined('APPLICATION_PATH') || define('APPLICATION_PATH', $dir);
require_once(APPLICATION_PATH . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'config.php');
$app = new Yaf_Application(BILLRUN_CONFIG_PATH);
$app->bootstrap();
Yaf_Loader::getInstance(APPLICATION_PATH . '/application/modules/Billapi')->registerLocalNamespace("Models");

/**
 * Reset and update linking fields between bills (invoices, payments)
 * @param array $accounts Account ids
 */
function rebuildBillsLinks($accounts) {
	echo 'Rebuilding links...' . PHP_EOL;
	if ($accounts) {
		$billsColl = Billrun_Factory::db()->billsCollection();
		foreach ($accounts as $aid) {
			echo 'Rebuilding links to ' . $aid . PHP_EOL;
			$query = array(
				'aid' => $aid,
			);
			$res = $billsColl->query($query)->cursor();
			foreach ($res as $bill) {
				$bill->collection($billsColl);
				unset($bill['pays']);
				unset($bill['left']);
				unset($bill['paid_by']);
				unset($bill['paid']);
				unset($bill['total_paid']);
				unset($bill['waiting_payments']);
				if ($bill['due'] < 0) {
					$bill['left'] = $bill['amount'];
				} else {
					$bill['total_paid'] = 0;
					$bill['left_to_pay'] = $bill['due'];
					if (isset($bill['vatable_left_to_pay'])) {
						if (isset($bill['due_before_vat'])) {
							$bill['vatable_left_to_pay'] = $bill['due_before_vat'];
						} else {
							unset($bill['vatable_left_to_pay']);
						}
					}
				}
				if (!$bill->save(1)) {
					echo 'Error resetting bill ' . ($bill['type'] == 'inv' ? $bill['invoice_id'] : $bill['txid']) . PHP_EOL;
				}
			}

			rebuildRejectionsAndCancelledLinks($aid);	
			Billrun_Bill::payUnpaidBillsByOverPayingBills($aid, false);
		}
	}
}

function rebuildRejectionsAndCancelledLinks($aid) {
	$rejections = Billrun_Bill_Payment::getRejectionPayments($aid);
	$cancellations = Billrun_Bill_Payment::getCancellationPayments($aid);
	$matchedPayments = array();
	$complementaryPayments = array();
	$originalPayments = array();
	
	foreach (array('rej' => $rejections, 'can' => $cancellations) as $key => $payments) {
		foreach ($payments as $payment) {
			$linkField = ($key == 'rej') ?  'original_txid' : 'cancel';
			$originalPaymentField = ($key == 'rej') ?  'rejected' : 'cancelled';
			$complementaryPaymentField = ($key == 'rej') ?  'rejection' : 'cancel';
			if (isset($payment[$complementaryPaymentField]) && $payment[$complementaryPaymentField] == true) {
				$complementaryPayments[$key][$payment[$linkField]] = $payment;
			}
			if (isset($payment[$originalPaymentField]) && $payment[$originalPaymentField] == true) {
				$originalPayments[$key][$payment['txid']] = $payment;
			}	
		}
	
		$originalPayments = isset($originalPayments[$key]) ? $originalPayments[$key] : array();
		$complementaryPayments = isset($complementaryPayments[$key]) ? $complementaryPayments[$key] : array();
		foreach ($originalPayments as $txId => $originalObj) {
			foreach ($complementaryPayments as $originalTxId => $complementaryObj) {
				if ($txId == $originalTxId) {
					$matchedPayments[] = array($originalPaymentField => $originalObj, $complementaryPaymentField => $complementaryObj);
				}
			}
		}
		foreach ($matchedPayments as $matchedPayment) {
			if (isset($matchedPayment[$originalPaymentField]['pays']) || isset($matchedPayment[$originalPaymentField]['paid_by'])) {
				continue;
			}
			$origPay = Billrun_Bill::getInstanceByData($matchedPayment[$originalPaymentField]);
			$complPay = Billrun_Bill::getInstanceByData($matchedPayment[$complementaryPaymentField]);
			if ($matchedPayment[$originalPaymentField]['due'] < 0) {
				$unpaidBillRaw = current(Billrun_Bill::getUnpaidBills(array('aid' => $aid, '$and' => array(array('due' => $matchedPayment[$complementaryPaymentField]['due'])))));
				if ($unpaidBillRaw == false) {
					$unpaidBillRaw = current(Billrun_Bill::getUnpaidBills(array('aid' => $aid, '$and' => array(array('due' => array('$gt' => $matchedPayment[$complementaryPaymentField]['due']))))));
					if ($unpaidBillRaw == false) {
						continue;
					}
				}
				$unpaidBill = Billrun_Bill::getInstanceByData($unpaidBillRaw);
				$origPay->attachPaidBill($unpaidBill->getType(), $unpaidBill->getId(), $matchedPayment[$complementaryPaymentField]['due'])->save();
				$complPay->attachPaidBill($unpaidBill->getType(), $unpaidBill->getId(), $matchedPayment[$complementaryPaymentField]['due'])->save();
			} else {
				$overPayingBill = current(Billrun_Bill::getOverPayingBills(array('aid' => $aid, 'due' => $matchedPayment[$complementaryPaymentField]['due'])));
				if ($overPayingBill == false) {
					$overPayingBill = current(Billrun_Bill::getOverPayingBills(array('aid' => $aid, 'due' => array('$lt' => $matchedPayment[$complementaryPaymentField]['due']))));
					if ($overPayingBill == false) {
						continue;
					}
				}
				$origPay->attachPayingBill($overPayingBill, $matchedPayment[$originalPaymentField]['due'])->save();
				$complPay->attachPayingBill($overPayingBill, $matchedPayment[$originalPaymentField]['due'])->save();
			}
		}
	}
}

$aids = getopt(null, ["accounts:"]);
$accounts = Billrun_Util::verify_array(explode(',', $aids['accounts']), 'int');
rebuildBillsLinks($accounts);
