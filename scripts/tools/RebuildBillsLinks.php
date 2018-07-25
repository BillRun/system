<?php

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
				if (!$bill->save($billsColl, 1)) {
					echo 'Error resetting bill ' . ($bill['type'] == 'inv' ? $bill['invoice_id'] : $bill['txid']) . PHP_EOL;
				}
			}
			Billrun_Bill::payUnpaidBillsByOverPayingBills($aid, false);
		}
	}
}