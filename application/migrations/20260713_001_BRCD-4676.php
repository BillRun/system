<?php

/**
 * BRCD-4676 - Zero the remaining balance on voided (rejected/cancelled/denied)
 * bills so such a bill no longer carries a positive left / left_to_pay, and add
 * sparse indexes on left / left_to_pay.
 *
 * "Voided" is the logical complement of
 * Billrun_Bill::getNotRejectedOrCancelledQuery() (OR-joined).
 */
return new class extends Billrun_Migration_Base {

	public function getTaskCode() {
		return 'BRCD-4676';
	}

	public function run() {
		$bills = $this->db->billsCollection();

		$voided = [
			['rejected' => true],
			['rejection' => true],
			['cancelled' => true],
			['cancel' => ['$exists' => true]],
			['is_denial' => true],
			['denied_by' => ['$exists' => true]],
		];

		// A bill carries either `left` or `left_to_pay` (never both), so each field
		// is updated under its own scoped query - this clears the field that exists
		// without introducing the one that does not.
		foreach (['left', 'left_to_pay'] as $field) {
			$this->log("BRCD-4676: zeroing $field on rejected/cancelled/denied bills");
			$query = ['$or' => $voided, $field => ['$gt' => 0]];
			$res = $bills->update(
				$query,
				['$set' => [$field => 0]],
				['multiple' => true]
			);
			$this->log(sprintf(
				"BRCD-4676: %s -> 0 on %d bill(s) (matched %d)",
				$field,
				isset($res['nModified']) ? $res['nModified'] : 0,
				isset($res['n']) ? $res['n'] : 0
			));
		}

		foreach (['left', 'left_to_pay'] as $field) {
			$this->log("BRCD-4676: creating index on $field");
			$bills->createIndex([$field => 1], ['sparse' => true, 'background' => true]);
		}

		
	}

};
