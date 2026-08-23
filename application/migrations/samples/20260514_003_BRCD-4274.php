<?php

/**
 * BRCD-4274 - Insert notification plugin into config.plugins if missing.
 */
return new class extends Billrun_Migration_Base {

	public function getTaskCode() {
		return 'BRCD-4274';
	}

	public function run() {
		if (!isset($this->lastConfig['plugins']) || !is_array($this->lastConfig['plugins'])) {
			$this->lastConfig['plugins'] = [];
		}
		foreach ($this->lastConfig['plugins'] as $plugin) {
			if (is_array($plugin) && isset($plugin['name']) && $plugin['name'] === 'notificationsPlugin') {
				return;
			}
		}
		$this->lastConfig['plugins'][] = [
			'name' => 'notificationsPlugin',
			'enabled' => false,
			'system' => true,
			'hide_from_ui' => false,
		];
	}

};
