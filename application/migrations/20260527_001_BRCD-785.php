<?php

/**
 * BRCD-785 - Customer hierarchy.
 *
 * Adds the three system account fields that model the account tree and an index
 * on `parent_aid` (used by the aggregator subtree walk and payer-ancestor lookups):
 *   - parent_aid     : reference to the parent account (null/absent = root).
 *   - issues_invoice : whether the account issues its own invoice (default true).
 *   - is_payer       : whether the account pays its own bills (default true).
 */
return new class extends Billrun_Migration_Base {

	public function getTaskCode() {
		return 'BRCD-785';
	}

	public function run() {
		$accountFields = [
			[
				'field_name' => 'parent_aid',
				'system' => true,
				'type' => 'integer',
				'editable' => true,
				'display' => true,
				'show_in_list' => false,
				'unique' => false,
				'mandatory' => false,
				'default_value' => null,
				'title' => 'Parent account',
			],
			[
				'field_name' => 'issues_invoice',
				'system' => true,
				'type' => 'boolean',
				'editable' => true,
				'display' => true,
				'show_in_list' => false,
				'mandatory' => false,
				'default_value' => true,
				'title' => 'Issues invoice',
			],
			[
				'field_name' => 'is_payer',
				'system' => true,
				'type' => 'boolean',
				'editable' => true,
				'display' => true,
				'show_in_list' => false,
				'mandatory' => false,
				'default_value' => true,
				'title' => 'Is payer',
			],
		];

		foreach ($accountFields as $field) {
			$this->addFieldToConfig($this->lastConfig['subscribers'], 'account', $field);
		}

		$this->db->subscribersCollection()->createIndex(
			['parent_aid' => 1],
			['unique' => false, 'sparse' => true, 'background' => true]
		);
	}

};
