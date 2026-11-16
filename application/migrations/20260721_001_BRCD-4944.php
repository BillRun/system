<?php

/**
 * BRCD-4944 - Move export generator definitions from config (export_generators)
 * to the export_generators collection.
 */
return new class extends Billrun_Migration_Base {

	public function getTaskCode() {
		return 'BRCD-4944';
	}

	public function run() {
		$generators = $this->lastConfig['export_generators'] ?? null;
		if (!is_array($generators)) {
			return;
		}
		$collection = $this->db->export_generatorsCollection();
		$from = new Mongodloid_Date();
		$to = new Mongodloid_Date(strtotime('+100 years'));
		foreach ($generators as $generator) {
			if (empty($generator['name'])) {
				continue;
			}
			$generator['from'] = $from;
			$generator['to'] = $to;
			$collection->update(['name' => $generator['name']], ['$set' => $generator], ['upsert' => true]);
			$this->log('BRCD-4944: migrated export generator ' . $generator['name']);
		}
		$this->lastConfig['export_generators'] = [];
	}

};
