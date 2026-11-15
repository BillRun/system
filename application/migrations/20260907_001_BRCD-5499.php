<?php

/**
 * BRCD-5499 - Update jsignpdf exec_path to the bundled jsignpdf 3.2.1 launcher script.
 */
return new class extends Billrun_Migration_Base {

	public function getTaskCode() {
		return 'BRCD-5499';
	}

	public function run() {
		if (!isset($this->lastConfig['signer']['jsignpdf']) || !is_array($this->lastConfig['signer']['jsignpdf'])) {
			return;
		}
		$this->lastConfig['signer']['jsignpdf']['exec_path'] = '/opt/jsignpdf-3.2.1/bin/jsignpdf.sh';
	}

};
