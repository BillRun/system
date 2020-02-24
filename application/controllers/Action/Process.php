<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Process action controller class
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       1.0
 */
class ProcessAction extends Action_Base {
	use Billrun_Traits_TypeAll;
	
	/**
	 * method to execute the process process
	 * it's called automatically by the cli main controller
	 */
	public function execute() {

		if (!$this->isOn()) {
			$this->getController()->addOutput(ucfirst($this->getRequest()->action) . " is off");
			return;
		}

		$possibleOptions = array(
			'type' => false,
			'path' => true,
			'backup' => true, // backup path
		);

		if (($options = $this->getController()->getInstanceOptions($possibleOptions)) === FALSE) {
			return;
		}

		$extraParams = $this->_controller->getParameters();
		if (!empty($extraParams)) {
			$options = array_merge($extraParams, $options);
		}
		// If not type all process normaly.
		if(!$this->handleTypeAll($options)) {
			$this->loadProcessor($options);	
		}
	}
	
	protected function loadProcessor($options) {
		$this->_controller->addOutput("Loading processor");
		$processor = Billrun_Processor::getInstance($options);

		if (!$processor) {
			$this->_controller->addOutput("Processor cannot be loaded");
			return;
		}
		$this->_controller->addOutput("Starting to process. This action can take a while...");
		// buffer all action output
		ob_start();
		if (isset($options['path']) && $options['path']) {
			$linesProcessedCount = $processor->process();
		} else {
			$linesProcessedCount = $processor->process_files();
		}
		// write the buffer into log and output
		$this->_controller->addOutput("processed " . $linesProcessedCount . " lines");
		$this->_controller->addOutput(ob_get_contents());
		ob_end_clean();
	}
	
	protected function getHandleFunction() {
		return "loadProcessor";
	}

	protected function getNameType() {
		return "processor";
	}
	
	protected function getCMD() {
		return 'php ' . APPLICATION_PATH . '/public/index.php --env ' . Billrun_Factory::config()->getEnv() . '  --tenant ' . Billrun_Factory::config()->getTenant() . ' --process --type';
	}
}
