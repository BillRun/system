<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Generate action controller class
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       1.0
 */
class ApigenerateAction extends Action_Base {

	/**
	 * method to execute the generate process
	 * it's called automatically by the cli main controller
	 */
	public function execute() {
		$ret = false;
		if (($options = $this->_controller->getInstanceOptions(array('type'=> false))) === FALSE) {
			return "Please provide options";
		}
		Billrun_Factory::log(print_r($options,1));
		$generator = Billrun_Generator::getInstance($options);
		Billrun_Factory::log("Instance Returned :".print_r($generator,1));
		if ($generator) {
			Billrun_Factory::log("Generator Loaded Loading Data...");
			$generator->load();
			Billrun_Factory::log("Generator Loaded, Generating...");
			$ret = $generator->generate();
			Billrun_Factory::log("Generation Finished Returning Results");
			if($ret) {	
				Billrun_Factory::log($ret);
					$this->_controller->setOutput($ret);
				}
		} else {
			Billrun_Factory::log("Generator cannot be loaded");
			$this->_controller->setOutput("Generator cannot be loaded");
		}
		Billrun_Factory::log("Generator Finished.");
	}

}