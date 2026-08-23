<?php

/**
 * BRCD-2251 - Remove the legacy 'vatable' field from services config.
 */
return new class extends Billrun_Migration_Base {

	public function getTaskCode() {
		return 'BRCD-2251';
	}

	public function run() {
		$this->removeFieldFromConfig($this->lastConfig, 'services', 'vatable');
	}

};
