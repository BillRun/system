<?php


//$dir = 'billrun project directory';
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

			rebuildRejectionsLinks($aid);
			Billrun_Bill::payUnpaidBillsByOverPayingBills($aid, false);
		}
	}
}

function rebuildRejectionsLinks($aid) {
	$matchedRejectedPayments = array();
	$rejectedPayments = array();
	$rejections = Billrun_Bill_Payment::getRejectionPayments();
	foreach ($rejections as $rejection) {
		if (isset($rejection['rejection']) && $rejection['rejection'] == true) {
			$rejectionPayments[$rejection['original_txid']] = $rejection;
		}
		if (isset($rejection['rejected']) && $rejection['rejected'] == true) {
			$rejectedPayments[$rejection['txid']] = $rejection;
		}
	}
	foreach ($rejectedPayments as $txId => $rejectedObj) {
		foreach ($rejectionPayments as $originalTxId => $rejectionObj) {
			if ($txId == $originalTxId) {
				$matchedRejectedPayments[] = array('rejected' => $rejectedObj, 'rejection' => $rejectionObj);
			}
		}
	}
	foreach ($matchedRejectedPayments as $matchedRejectedPayment) {
		if (isset($matchedRejectedPayment['rejected']['pays']) || isset($matchedRejectedPayment['rejected']['paid_by'])) {
			continue;
		}
		$rejectedPay = Billrun_Bill::getInstanceByData($matchedRejectedPayment['rejected']);
		$rejectionPay = Billrun_Bill::getInstanceByData($matchedRejectedPayment['rejection']);
		if ($matchedRejectedPayment['rejected']['due'] < 0) {
			$unpaidBillRaw = current(Billrun_Bill::getUnpaidBills(array('aid' => $aid, 'due' => $matchedRejectedPayment['rejection']['due'])));
			$unpaidBill = Billrun_Bill::getInstanceByData($unpaidBillRaw);
			$rejectedPay->attachPaidBill($unpaidBill->getType(), $unpaidBill->getId(), $matchedRejectedPayment['rejection']['due'])->save();
			$rejectionPay->attachPaidBill($unpaidBill->getType(), $unpaidBill->getId(), $matchedRejectedPayment['rejection']['due'])->save();
		} else {
			$overPayingBill = current(Billrun_Bill::getOverPayingBills(array('aid' => $aid, 'due' => $matchedRejectedPayment['rejection']['due'])));
			$rejectedPay->attachPayingBill($overPayingBill, $matchedRejectedPayment['rejected']['due'])->save();
			$rejectionPay->attachPayingBill($overPayingBill, $matchedRejectedPayment['rejected']['due'], null, true)->save();
		}
	}
}

$aids = getopt(null, ["accounts:"]);
$accounts = Billrun_Util::verify_array(explode(',', $aids['accounts']), 'int');
rebuildBillsLinks($accounts);
