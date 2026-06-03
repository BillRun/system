<?php

/**
 * BRCD-938 - Option to not generate pdfs for the cycle (default true).
 */
return new class extends Billrun_Migration_Base {

	public function getTaskCode() {
		return 'BRCD-938';
	}

	public function run() {
		if (!isset($this->lastConfig['billrun']['generate_pdf'])) {
			$this->lastConfig['billrun']['generate_pdf'] = ['v' => true, 't' => 'Boolean'];
		}
	}

};
