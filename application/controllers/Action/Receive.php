<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Receive action controller class
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       1.0
 */
class ReceiveAction extends Action_Base {
	use Billrun_Traits_TypeAll;
	
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
			'path' => true,
			'workspace' => true,
		);

		if (($options = $this->_controller->getInstanceOptions($possibleOptions)) === FALSE) {
			return;
		}

		// If not type all process normaly.
		if(!$this->handleTypeAll($options)) {
			$connectionsPerReceiverType = array();
			$inputProcessor = Billrun_Factory::config()->getFileTypeSettings($options['type'], true);
			$connections = isset($inputProcessor['receiver']['connections']) ? $inputProcessor['receiver']['connections'] : [];
			foreach ($connections as $connection) {
				$connectionsPerReceiverType[$connection['receiver_type']][] = $connection;
			}
			
			foreach ($connectionsPerReceiverType as $receiverType => $receiverTypeConnections) {
				$inputProcessor['receiver']['connections'] = $receiverTypeConnections;
				$inputProcessor['receiver']['receiver_type'] = $receiverType;
				$options = $inputProcessor;
				$this->loadReceiver($options);
			}
		}
	}

	protected function loadReceiver($options) {
		$this->getController()->addOutput("Loading receiver");
		$receiver = Billrun_Receiver::getInstance($options);

		if (!$receiver) {
			$this->getController()->addOutput("Receiver cannot be loaded");
			return;
		}

		$this->getController()->addOutput("Starting to receive. This action can take a while...");
		$files = $receiver->receive();
		$this->getController()->addOutput("Received " . count($files) . " files");
	}
	
	protected function getHandleFunction() {
		return "loadReceiver";
	}
	
	protected function getCMD() {
		return 'php ' . APPLICATION_PATH . '/public/index.php --env ' . Billrun_Factory::config()->getEnv() . '  --tenant ' . Billrun_Factory::config()->getTenant() . ' --receive --type';
	}

	protected function getNameType() {
		return "receiver";
	}

}
