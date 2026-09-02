<?php

/**
 * BRCD-1077 - Add custom 'tariff_category' field to Products (Rates) config.
 */
return new class extends Billrun_Migration_Base {

	public function getTaskCode() {
		return 'BRCD-1077';
	}

	public function run() {
		$this->addFieldToConfig($this->lastConfig, 'rates', [
			'system' => false,
			'select_list' => true,
			'display' => true,
			'editable' => true,
			'field_name' => 'tariff_category',
			'default_value' => 'retail',
			'show_in_list' => true,
			'title' => 'Tariff category',
			'mandatory' => true,
			'select_options' => 'retail',
			'changeable_props' => ['select_options'],
		]);
	}

};
