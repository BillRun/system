<?php

/**
 * BRCD-4649 - App-level encryption of database fields.
 *
 * Generate and store a field-encryption key in the config for existing
 * environments that don't already have one. Skipped when the key is supplied
 * via the environment (BR_ENCRYPTION_KEY / BR_ENCRYPTION_KEY_FILE), which keeps
 * the key out of the DB and takes precedence at runtime.
 */
return new class extends Billrun_Migration_Base {

	public function getTaskCode() {
		return 'BRCD-4649';
	}

	public function run() {
		if (Billrun_Utils_Encryption::ensureConfigKey($this->lastConfig)) {
			$this->log('BRCD-4649: generated field-encryption key (encryption.key) in config');
		} else {
			$this->log('BRCD-4649: encryption key already present or provided via environment; skipping');
		}
	}

};
