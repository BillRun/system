<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2017 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * EventsNotifier action controller class
 *
 * @author idan
 */
class NotifyAction extends Action_Base {

	public function execute() {
		$possibleOptions = array(
			'type' => true,
		);

		if (($options = $this->_controller->getInstanceOptions($possibleOptions)) === FALSE) {
			return;
		}
		$this->getController()->addOutput("Notify on events");
		Billrun_Factory::eventsManager()->notify();
		$this->getController()->addOutput("Notifying Done");
	}

}
