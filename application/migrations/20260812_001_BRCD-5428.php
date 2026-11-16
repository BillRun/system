<?php

/**
 * BRCD-5428 - Products groups: create the product_groups collection (indexes)
 * and seed the productgroups entity fields into the config.
 */
return new class extends Billrun_Migration_Base {

	public function getTaskCode() {
		return 'BRCD-5428';
	}

	public function run() {
		$collection = $this->db->productgroupsCollection();
		$collection->createIndex(['key' => 1, 'from' => 1, 'to' => 1], ['background' => true]);
		$collection->createIndex(['key' => 1, 'to' => 1], ['sparse' => true, 'background' => true]);
		$collection->createIndex(['description' => 1], ['background' => true]);
		foreach ($this->getFields() as $field) {
			$this->addFieldToConfig($this->lastConfig, 'productgroups', $field);
		}
	}

	protected function getFields() {
		return [
			['field_name' => 'key', 'system' => true, 'mandatory' => true],
			['field_name' => 'from', 'system' => true, 'mandatory' => true, 'type' => 'date'],
			['field_name' => 'to', 'system' => true, 'mandatory' => true, 'type' => 'date'],
			['field_name' => 'description', 'system' => true, 'mandatory' => true],
			['field_name' => 'property_type', 'system' => true],
			['field_name' => 'usage_types', 'system' => true],
			['field_name' => 'rates', 'system' => true, 'mandatory' => true],
		];
	}

};
