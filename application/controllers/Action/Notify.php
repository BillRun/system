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
		$extraParams = $this->_controller->getParameters();
		if (!empty($extraParams)) {
			$options = array_merge($extraParams, $options);
		}

		$this->getController()->addOutput("Notify on " . $options['type']);
		switch ($options['type']) {
			case 'email':
				$this->notifyEmail($options);
			case 'events':
			default:
				$this->notifyEvents();
		}
		$this->getController()->addOutput("Notifying Done");
	}
	
	protected function notifyEvents() {
		Billrun_Factory::eventsManager()->notify();
	}
	
	protected function notifyEmail($options = array()) {
		if ($options['email_type'] == 'invoiceReady' && (empty($options['invoices']) && empty($options['billrun_key']))) {
			$this->getController()->addOutput("Notifying InvoiceReady email from CLI must contain invoices");
			return false;
		}
		Billrun_Factory::emailSenderManager($options)->notify();
	}

}
