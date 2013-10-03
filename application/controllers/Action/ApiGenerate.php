7<?php

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
class ApiGenerateAction extends Action_Base {

	/**
	 * method to execute the generate process
	 * it's called automatically by the cli main controller
	 */
	public function execute() {

		if (($options = $this->_controller->getInstanceOptions(array('type'=> false))) === FALSE) {
			return;
		}

		$generator = Billrun_Generator::getInstance($options);

		if ($generator) {
			$generator->load();
			$results = $generator->generate();
			if($results) {	
					$this->getController()->setOutput($results);
				}
		} else {
			$this->_controller->addOutput("Generator cannot be loaded");
		}
	}

}