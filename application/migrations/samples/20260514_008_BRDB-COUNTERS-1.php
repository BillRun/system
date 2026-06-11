<?php

/**
 * Drop the legacy `coll_1_oid_1` index on counters and create the `{coll, key}` index.
 *
 * Source: mongo/migration/script.js lines 493-494 - no ticket attached in the JS, so
 * this migration is given a synthetic task code.
 */
return new class extends Billrun_Migration_Base {

	public function getTaskCode() {
		return 'BRDB-COUNTERS-1';
	}

	public function run() {
		$counters = $this->db->countersCollection();
		$counters->dropIndexIfExists('coll_1_oid_1');
		$counters->createIndex(['coll' => 1, 'key' => 1], ['sparse' => false, 'background' => true]);
	}

};
