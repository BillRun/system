<?php

/**
 * BRCD-4649-1 (sample) - Add a searchable ENCRYPTED custom field to the account level.
 *
 * Cookbook example for the billapi `encrypted` field type. Copy this file into
 * application/migrations/ (and bump the date/sequence) to run it with
 * `php public/index.php --env <env> --dbmigrate`.
 *
 * Account custom fields live under `subscribers.account.fields` (note: the
 * accounts billapi collection_name is "subscribers"), which is one level deeper
 * than the addFieldToConfig() helper handles - so the field is appended here
 * directly, idempotently by field_name.
 *
 * Field of interest: type => 'encrypted'
 *   - stored encrypted at rest (deterministic AES-256-CTR),
 *   - decrypted automatically on billapi get,
 *   - searchable => true lets you query the account by the plaintext value
 *     (exact match) because the encryption is deterministic.
 *
 * Try it after migrating:
 *   - create an account with national_id=123456789 (POST /api/accounts ... create)
 *   - read it back (GET /api/accounts ... get) -> you get 123456789 in clear
 *   - inspect the raw mongo doc -> the stored value is `enc:v1:...`
 *   - query accounts by national_id=123456789 -> the matching account is found
 */
return new class extends Billrun_Migration_Base {

	public function getTaskCode() {
		return 'BRCD-4649-1';
	}

	public function run() {
		$field = [
			'field_name'   => 'national_id',
			'title'        => 'National ID',
			'type'         => 'encrypted',
			'system'       => false,
			'display'      => true,
			'editable'     => true,
			'searchable'   => true,
			'unique'       => false,
			'mandatory'    => false,
			'show_in_list' => false,
		];

		if (!isset($this->lastConfig['subscribers']['account']['fields'])
			|| !is_array($this->lastConfig['subscribers']['account']['fields'])) {
			$this->lastConfig['subscribers']['account']['fields'] = [];
		}

		foreach ($this->lastConfig['subscribers']['account']['fields'] as $existing) {
			if (isset($existing['field_name']) && $existing['field_name'] === $field['field_name']) {
				$this->log('BRCD-4649-1: account field ' . $field['field_name'] . ' already exists; skipping');
				return;
			}
		}

		$this->lastConfig['subscribers']['account']['fields'][] = $field;
		$this->log('BRCD-4649-1: added encrypted account field ' . $field['field_name']);
	}

};
