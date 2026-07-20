<?php

/**
 * @package         Billing
 */

/**
 * Send action controller class
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       
 */

 /**
 * CLI command to resend unsuccessfully sent files.
 * Handles failed uploads from 'export_generators' and 'payment_gateways'.
 */
class Send_fileAction extends Action_Base {

	use Billrun_Traits_Api_FlexibleOperationsLock;

	protected $export_name;

	public function execute() {

		$possibleOptions = array(
			'type' => false,
			'stamp' => true,
		);

		if (($options = $this->_controller->getInstanceOptions($possibleOptions)) === FALSE) {
			return;
		}

		$this->_controller->addOutput("Loading files sender..");

		$extraParams = $this->_controller->getParameters();
		if (!empty($extraParams)) {
			$options = array_merge($extraParams, $options);
		}

		$lockAcquiredByThisProcess = false;
		try {
			$send_model = new SendFileModel($options, $extraParams);
			$sender_details = $send_model->getSenderDetails();
			if (!$sender_details) {
				$this->_controller->addOutput('Something went wrong while building the sender. Nothing was sent.');
				return false;
			}
			$this->export_name = $options['name'];
			$exportLockId = [
				'action'     => 'send_file',
				'filtration' => 'send_' . $this->export_name,
			];

			if ($this->lock($exportLockId, 6)) {
				$lockAcquiredByThisProcess = true;
			} else {
				Billrun_Factory::log("Sending file is already running", Zend_Log::NOTICE);
				return;
			}

			foreach ($send_model->connections as $connection) {
				$this->_controller->addOutput("Move to sender {$connection['name']} - start");
				$sender = Billrun_Sender::getInstance($connection);
				if (!$sender) {
					$this->_controller->addOutput("Cannot get sender. details: " . print_R($connection, 1));
					return;
				}
				$this->_controller->addOutput("Sender loaded");
				$this->_controller->addOutput("Starting to send the files. This action can take a while...");
				foreach ($send_model->log_documents as $file_log) {
					if (!empty($file_log['path'])) {
						$this->_controller->addOutput("Trying to send : " . $file_log['file_name'] . ", logged with stamp : " . $file_log['stamp']);
						$this->_controller->addOutput("Local file path: " . $file_log['path']);
						if (!$sender->send($file_log['path'])) {
							$this->_controller->addOutput("Move to sender {$connection['name']} - failed!");
							continue;
						} else {
							$this->_controller->addOutput("Move to sender {$connection['name']} - done");
							$this->_controller->addOutput("Updating log document with the export time..");
							$send_model->updateDbLogRecord($file_log);
						}
					} else {
						$this->_controller->addOutput("Missing file's path in the log document, stamp: " . $file_log['stamp']);
						$this->_controller->addOutput("Moving on..");
						continue;
					}
				}
			}
		} catch (Exception $ex) {
			$this->_controller->addOutput($ex->getMessage());
			$this->_controller->addOutput('Something went wrong while building the sender. Nothing was sent.');
			return;
		} finally {
				if ($lockAcquiredByThisProcess && !$this->release($exportLockId)) {
				Billrun_Factory::log("Send_file: Issue in releasing operation lock", Zend_Log::ALERT);
			}
		}

		$this->_controller->addOutput("Finished sending.");
	}

}
