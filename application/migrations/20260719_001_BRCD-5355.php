<?php

/**
 * BRCD-5355 - Move event definitions from config (events.balance / events.fraud)
 * to the event_settings collection.
 */
return new class extends Billrun_Migration_Base {

	protected $usedKeys = [];

	public function getTaskCode() {
		return 'BRCD-5355';
	}

	public function run() {
		if (empty($this->lastConfig['events'])) {
			return;
		}
		$collection = $this->db->eventsettingsCollection();
		$collection->createIndex(['key' => 1], ['unique' => true, 'background' => true]);
		$collection->createIndex(['type' => 1, 'from' => 1, 'to' => 1], ['background' => true]);
		$now = new Mongodloid_Date();
		$farFuture = new Mongodloid_Date(strtotime('+100 years'));
		foreach (['balance', 'fraud'] as $type) {
			$definitions = $this->lastConfig['events'][$type] ?? null;
			if (!is_array($definitions)) {
				continue;
			}
			foreach ($definitions as $definition) {
				$definition['type'] = $type;
				$definition['key'] = $this->makeUniqueKey($definition['event_code'] ?? '');
				$definition['from'] = $now;
				$definition['to'] = $farFuture;
				$collection->update(['key' => $definition['key']], ['$set' => $definition], ['upsert' => true]);
			}
			unset($this->lastConfig['events'][$type]);
		}
	}

	protected function makeUniqueKey($eventCode) {
		$base = preg_replace('/[^A-Z0-9_]/', '_', strtoupper((string) $eventCode));
		if ($base === '') {
			$base = 'EVENT';
		}
		$key = $base;
		$suffix = 1;
		while (isset($this->usedKeys[$key])) {
			$suffix++;
			$key = $base . $suffix;
		}
		$this->usedKeys[$key] = true;
		return $key;
	}

};
