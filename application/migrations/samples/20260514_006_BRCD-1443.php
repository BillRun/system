<?php

/**
 * BRCD-1443 - Fix billrun docs whose attributes.invoice_type is wrong after a rebalance.
 */
return new class extends Billrun_Migration_Base {

	public function getTaskCode() {
		return 'BRCD-1443';
	}

	public function run() {
		$this->db->billrunCollection()->update(
			[
				'attributes.invoice_type' => ['$ne' => 'immediate'],
				'billrun_key' => ['$regex' => '^[0-9]{14}$'],
			],
			['$set' => ['attributes.invoice_type' => 'immediate']],
			['multiple' => true]
		);
	}

};
