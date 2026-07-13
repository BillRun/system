<?php

/**
 * BRCD-4676 - Zero the remaining balance on voided (rejected/cancelled/denied)
 * bills and enforce that such a bill can never carry a balance again.
 *
 * A "voided" bill is the logical complement of
 * Billrun_Bill::getNotRejectedOrCancelledQuery() (OR-joined) - it matches at
 * least one of: rejected, rejection, cancelled, cancel exists, is_denial,
 * denied_by exists.
 *
 * 1. Zero whichever remaining amount is still positive (> 0) on every voided
 *    bill. A bill carries either `left` or `left_to_pay` (never both), so each
 *    field is updated under its own scoped query - this clears the field that
 *    exists without introducing the one that does not.
 * 2. Create partial indexes on left / left_to_pay (> 0).
 * 3. Install a collection validator that blocks writing a voided bill that still
 *    holds a positive left / left_to_pay. Step 1 makes all existing bills
 *    compliant; validationLevel "moderate" is used defensively so that any
 *    voided bill outside the zeroing predicate (e.g. a negative balance) is not
 *    blocked from further updates - inserts and updates of valid docs are
 *    always checked.
 */
return new class extends Billrun_Migration_Base {

	public function getTaskCode() {
		return 'BRCD-4676';
	}

	public function run() {
		$bills = $this->db->billsCollection();

		// Clauses matching a voided bill - the logical complement of
		// Billrun_Bill::getNotRejectedOrCancelledQuery(). Defined once and reused
		// by both the zeroing queries and the validator so the two cannot drift.
		$voided = [
			['rejected' => true],
			['rejection' => true],
			['cancelled' => true],
			['cancel' => ['$exists' => true]],
			['is_denial' => true],
			['denied_by' => ['$exists' => true]],
		];

		$this->log("BRCD-4676: zeroing left/left_to_pay on rejected/cancelled/denied bills");
		foreach (['left', 'left_to_pay'] as $field) {
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

		$this->log("BRCD-4676: creating partial indexes on left / left_to_pay (> 0)");
		foreach (['left', 'left_to_pay'] as $field) {
			$bills->createIndex(
				[$field => 1],
				[
					'partialFilterExpression' => [$field => ['$gt' => 0]],
					'background' => true,
				]
			);
		}

		// A doc is valid when it does NOT match "voided AND positive balance",
		// hence the $nor wrapper.
		$forbidden = [
			'$and' => [
				['$or' => $voided],
				['$or' => [
					['left' => ['$gt' => 0]],
					['left_to_pay' => ['$gt' => 0]],
				]],
			],
		];

		$collName = $bills->getName();
		$this->log("BRCD-4676: installing validator on '$collName' (block voided bills with positive left/left_to_pay)");
		$this->db->command([
			'collMod' => $collName,
			'validator' => ['$nor' => [$forbidden]],
			'validationLevel' => 'moderate',
			'validationAction' => 'error',
		]);
	}

};
