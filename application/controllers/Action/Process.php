<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Process action controller class
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       1.0
 */
class ProcessAction extends Action_Base {

	/**
	 * method to execute the process process
	 * it's called automatically by the cli main controller
	 */
	public function execute() {
		
		$possibleOptions = array(
			'type' => false,
			'parser' => false,
			'path' => true,
			'backup' => true, // backup path
		);

		if (($options = $this->getController()->getInstanceOptions($possibleOptions)) === FALSE) {
			return;
		}

		$this->_controller->addOutput("Parser selected: " . $options['parser']);
		//$options['parser'] = Billrun_Parser::getInstance(array('type' => $options['parser']));

		$this->_controller->addOutput("Loading processor");
		$processor = Billrun_Processor::getInstance($options);
		$this->_controller->addOutput("Processor loaded");

		if ($processor) {
			$this->_controller->addOutput("Starting to process. This action can take awhile...");

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
		} else {
			$this->_controller->addOutput("Processor cannot be loaded");
		}
	}

}