<?php

/**
 * Replace the legacy queue indexes with the calc_name-based ones.
 *
 * Drops `hash_1_calc_time_1_type_1` and `urt_1_type_1`, then creates the
 * {hash, calc_time, urt} and two {calc_name, ...} indexes used by the
 * calculators. The chain-mode cross-stage pull filters calc_name with a
 * low-selectivity $in, so it is served branch-free by {calc_time, urt},
 * which extends (and replaces) the legacy single-field calc_time index.
 *
 * The legacy single-field calc_name index goes too: both {calc_name, ...}
 * compound indexes serve calc_name lookups by prefix, and on MongoDB < 4.2
 * (no update hint) it was the poor plan the marking update kept choosing.
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
		$queue->createIndex(['calc_time' => 1, 'urt' => 1], ['unique' => false, 'background' => true]);
		$queue->dropIndexIfExists('hash_1_calc_time_1_type_1');
		$queue->dropIndexIfExists('urt_1_type_1');
		$queue->dropIndexIfExists('calc_time_1');
		$queue->dropIndexIfExists('calc_name_1');
	}

};
