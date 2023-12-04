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

		if (($options = $this->getController()->getInstanceOptions($possibleOptions)) === FALSE) {
			return;
		}
		$extraParams = $this->getController()->getParameters();
		if (!empty($extraParams)) {
			$options = array_merge($extraParams, $options);
		}

		// If not type all process normaly.
		if(!$this->handleTypeAll($options)) {
			$connectionsPerReceiverType = array();
			if (isset($options['payment_gateway'])) {
				$paymentGatewayReceiver = Billrun_Receiver_NonCDRs_PaymentGateway::getReceiverSettings($options);
				$pgConnections = isset($paymentGatewayReceiver['connections']) ? $paymentGatewayReceiver['connections'] : [];
				foreach ($pgConnections as $pgConnection) {
					$pgOptions = $options;
					$pgOptions['file_type'] = $options['type'];
					$pgOptions['receiver']['connection'] = $pgConnection;
					$pgOptions['receiver']['receiver_type'] = 'PaymentGateway_' . $options['payment_gateway'] . '_' . ucfirst($options['type']);
					$this->loadReceiver($pgOptions);
				}
				if (empty($paymentGatewayReceiver)) {
					$customPaymentGateways = Billrun_PaymentGateway_Connection::getReceiverSettings($options);
					$this->getController()->addOutput("Custom payment gateways receiver action");
					foreach ($customPaymentGateways as $fileType => $fileTypeSettings) {
						if($fileType === $options['file_type']) {
							$this->getController()->addOutput("Receiving file type : " . $fileType);
							foreach ($fileTypeSettings['connections'] as $connectionDetails) {
								$connectionDetails['file_type'] = $fileType;
								$connectionDetails['type'] = str_replace('_', '', ucwords($options['payment_gateway'], '_')) . str_replace('_', '', ucwords($options['type'], '_'));
								$this->getController()->addOutput("Initilazing receiver connection");
								$connection = Billrun_Factory::paymentGatewayConnection($connectionDetails);
								$this->getController()->addOutput("Receiving files...");
								$files = $connection->receive();
								$this->getController()->addOutput("Received " . count($files) . " files");
							}
						}
					}
				}
			}
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
