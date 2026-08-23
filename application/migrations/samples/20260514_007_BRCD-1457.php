<?php

/**
 * BRCD-1457 - Fix creation_time field in subscriber services (was {sec, usec}, should be a Date).
 */
return new class extends Billrun_Migration_Base {

	public function getTaskCode() {
		return 'BRCD-1457';
	}

	public function run() {
		$subscribers = $this->db->subscribersCollection();
		$cursor = $subscribers->query([
			'type' => 'subscriber',
			'services.creation_time.sec' => ['$exists' => 1],
		])->cursor();
		foreach ($cursor as $entity) {
			$doc = $entity->getRawData();
			if (empty($doc['services']) || !is_array($doc['services'])) {
				continue;
			}
			$changed = false;
			foreach ($doc['services'] as $idx => $service) {
				if (!isset($service['creation_time'])) {
					if (isset($service['from'])) {
						$doc['services'][$idx]['creation_time'] = $service['from'];
						$changed = true;
					}
					continue;
				}
				$ct = $service['creation_time'];
				if (is_array($ct) && isset($ct['sec'])) {
					$sec = (int) $ct['sec'];
					$usec = isset($ct['usec']) ? (int) $ct['usec'] : 0;
					$doc['services'][$idx]['creation_time'] = new Mongodloid_Date($sec, $usec);
					$changed = true;
				}
			}
			if ($changed) {
				$subscribers->update(['_id' => $doc['_id']], $doc);
			}
		}
	}

};
