<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Export action controller class
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       5.9
 */
class ExportAction extends Action_Base {

	/**
	 * method to execute the receive process
	 * it's called automatically by the cli main controller
	 */
	public function execute() {

		if (!$this->isOn()) {
			$this->getController()->addOutput(ucfirst($this->getRequest()->action) . " is off");
			return;
		}

		$possibleOptions = array(
			'type' => false,
		);

		if (($options = $this->getController()->getInstanceOptions($possibleOptions)) === FALSE) {
			return;
		}
		$export_generators_options = [];
		if (strtolower($options['type']) === 'all') {
			$export_generators = Billrun_Factory::config()->getConfigValue('export_generators');
			foreach ($export_generators as $export_generator) {
				$options['type'] = $export_generator['name'];
				$export_generators_options[] = $options;
			}
		} else {
			$export_generators_options[] = $options;
		}
		
		foreach ($export_generators_options as $export_generator_options) {
			$this->getController()->addOutput("Loading exporter");
			$exporter = Billrun_Exporter::getInstance($export_generator_options);
			$exporter_name = $exporter->getType();
			$this->getController()->addOutput("Exporter {$exporter_name} loaded");

			if ($exporter) {
				$this->getController()->addOutput("Starting to export. This action can take a while...");
				try {
					$exported = $exporter->export();
					$this->getController()->addOutput("Exported " . count($exported) . " lines");
				} catch (Exception $exc) {
					$this->getController()->addOutput("failed to execute export generator {$exporter_name}, error: {$exc->getMessage()}");
				}
			} else {
				$this->getController()->addOutput("Exporter cannot be loaded");
			}
		}
	}
	
}
