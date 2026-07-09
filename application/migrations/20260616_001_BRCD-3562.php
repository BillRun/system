<?php

/**
 * Replace the legacy queue indexes with the calc_name-based ones.
 *
 * Drops `hash_1_calc_time_1_type_1` and `urt_1_type_1`, then creates the
 * {hash, calc_time, urt} and two {calc_name, ...} indexes used by the
 * calculators.
 *
 * Source: mongo/migration/script.js runOnce(... 'BRCD-3562' ...).
 */
return new class extends Billrun_Migration_Base {

	public function getTaskCode() {
		return 'BRCD-3562';
	}

	public function run() {
		$queue = $this->db->queueCollection();
		$queue->createIndex(['hash' => 1, 'calc_time' => 1, 'urt' => 1], ['unique' => false, 'background' => true]);
		$queue->createIndex(['calc_name' => 1, 'type' => 1, 'calc_time' => 1, 'urt' => 1], ['unique' => false, 'background' => true]);
		$queue->createIndex(['calc_name' => 1, 'calc_time' => 1, 'urt' => 1], ['unique' => false, 'background' => true]);
		$queue->dropIndexIfExists('hash_1_calc_time_1_type_1');
		$queue->dropIndexIfExists('urt_1_type_1');
	}

};
