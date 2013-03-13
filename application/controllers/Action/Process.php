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

		$this->outputAdd("Parser selected: " . $options['parser']);
		//$options['parser'] = Billrun_Parser::getInstance(array('type' => $options['parser']));

		$this->outputAdd("Loading processor");
		$processor = Billrun_Processor::getInstance($options);
		$this->outputAdd("Processor loaded");

		if ($processor) {
			$this->outputAdd("Starting to process. This action can take awhile...");

			// buffer all action output
			ob_start();
			if (isset($options['path']) && $options['path']) {
				$lines = $processor->process();
			} else {
				$lines = $processor->process_files();
			}
			// write the buffer into log and output
			$this->outputAdd("processed " . count($lines) . " lines");
			$this->outputAdd(ob_get_contents());
			ob_end_clean();
		} else {
			$this->outputAdd("Processor cannot be loaded");
		}
	}

}