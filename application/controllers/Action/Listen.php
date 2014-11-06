<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Listen action controller class
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       1.0
 */
class ListenAction extends Action_Base {

	public function execute() {

		$possibleOptions = array(
			'type' => false,
			'host' => false,
			'port' => false,
		);

		if (($options = $this->_controller->getInstanceOptions($possibleOptions)) === FALSE) {
			return;
		}

		$this->getController()->addOutput("Loading listener");
		$listener = Billrun_Listener::getInstance($options);
		$this->getController()->addOutput("Listener loaded");

		if ($listener) {
			$this->getController()->addOutput("Starting to listen...");
			while (TRUE) {
				$data = $listener->listen();
				$listener->doAfterListen($data);
			}
		} else {
			$this->getController()->addOutput("Listener cannot be loaded");
		}
	}

}
