<?php

/**
 * BRCD-1077 (data backfill) - Set rates.tariff_category='retail' on docs missing it.
 */
return new class extends Billrun_Migration_Base {

	public function getTaskCode() {
		return 'BRCD-1077-1';
	}

	public function run() {
		$this->db->ratesCollection()->update(
			['tariff_category' => ['$exists' => false]],
			['$set' => ['tariff_category' => 'retail']],
			['multiple' => true]
		);
	}

};
