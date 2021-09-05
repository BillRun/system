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
class Send_fileAction extends Action_Base {

	use Billrun_Traits_Api_OperationsLock;

	protected $export_type;
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

		try {
			$sender_details = $this->getSenderDetails($options, $extraParams);
			$this->export_type = $options['type'];
			$this->export_name = $options['name'];
			if (!$sender_details) {
				$this->_controller->addOutput("No file was sent..");
				return;
			}
			foreach ($sender_details['connections'] as $connection) {
				$this->_controller->addOutput("Move to sender {$connection['name']} - start");
				$sender = Billrun_Sender::getInstance($connection);
				if (!$sender) {
					$this->_controller->addOutput("Cannot get sender. details: " . print_R($connections, 1));
					return;
				}
				$this->_controller->addOutput("Sender loaded");
				$this->_controller->addOutput("Starting to send the files. This action can take a while...");
				if (!$this->lock()) {
					Billrun_Factory::log("Sending file is already running", Zend_Log::NOTICE);
					return;
				}
				foreach ($sender_details['log_documents'] as $file_log) {
					if (!empty($file_log['path'])) {
						$this->_controller->addOutput("Trying to send : " . $file_log['file_name'] . ", logged with stamp : " . $file_log['stamp']);
						$this->_controller->addOutput("Local file path: " . $file_log['path']);
						if (!$sender->send($file_log['path'])) {
							$this->_controller->addOutput("Move to sender {$connection['name']} - failed!");
							continue;
						} else {
							$this->_controller->addOutput("Move to sender {$connection['name']} - done");
							$this->_controller->addOutput("Updating log document with the export time..");
							$this->updateDbLogRecord($file_log);
						}
					} else {
						$this->_controller->addOutput("Missing file's path in the log document, stamp: " . $file_log['stamp']);
						$this->_controller->addOutput("Moving on..");
						continue;
					}
				}
				if (!$this->release()) {
					Billrun_Factory::log("Problem in releasing operation", Zend_Log::ALERT);
					return;
				}
			}
		} catch (Exception $ex) {
			$this->_controller->addOutput($ex->getMessage());
			$this->_controller->addOutput('Something went wrong while building the sender. Nothing was sent.');
			if (!$this->release()) {
				Billrun_Factory::log("Problem in releasing operation", Zend_Log::ALERT);
				return;
			}
			return;
		}

		$this->_controller->addOutput("Finished sending.");
	}

	public function getSenderDetails($options, $extraParams) {
		if (!isset($options['type']) || !isset($extraParams['name'])) {
			throw new Exception("Missing type/name in the send file command..");
		}
		$data_type_config = Billrun_Factory::config()->getConfigValue($options['type'], []);
		$file_type_name = $extraParams['name'];
		if (empty($data_type_config)) {
			$this->_controller->addOutput('Didn\'t find configuration type : ' . $options['type']);
			return false;
		}
		$this->_controller->addOutput("Pulled " . $options['type'] . " configuration..");
		$file_type = current(array_filter($data_type_config, function($file_type) use($file_type_name) {
				return $file_type['name'] == $file_type_name;
			}));
		$this->_controller->addOutput("Pulled " . $file_type['name'] . " file type configuration..");
		$this->_controller->addOutput("Loading file type connections..");
		$res['connections'] = $this->getConfiguredConnections($file_type);
		$this->_controller->addOutput("Loading relevant log documents..");
		$res['log_documents'] = $this->getRelevantFilesLog($options, $file_type);
		return (!empty($res['connections']) && !empty($res['log_documents'])) ? $res : false;
	}

	public function getConfiguredConnections($file_type) {
		return !empty($file_type['senders']['connections']) ? $file_type['senders']['connections'] : [];
	}

	public function getRelevantFilesLog($options, $file_type) {
		$orphan_time = Billrun_Util::getIn($file_type['generator'], 'orphan_files_time', '6 hours');
		$query = [
			'source' => ($options['type'] == 'export_generators') ? 'export' : $options['type'],
			'name' => $options['name'],
			'$and' => array(
				array('export_start_time' => array('$lt' => new MongoDate(strtotime('-' . $orphan_time)))),
				array('exported_time' => array('$exists' => false)),
			)
		];
		if (isset($options['file_name'])) {
			$query['file_name'] = $options['file_name'];
		}
		$relevant_files = Billrun_Factory::db()->logCollection()->query($query)->cursor();
		if (count($relevant_files) == 0) {
			$this->_controller->addOutput("No files to send");
			return false;
		}
		$this->_controller->addOutput("Found " . count($relevant_files) . " files to send..");
		return $relevant_files;
	}

	public function updateDbLogRecord($file_log) {
		$update = ['exported_time' => new MongoDate()];
		$ret = Billrun_Factory::db()->logCollection()->update(array('stamp' => $file_log['stamp']), array('$set' => $update), array('w' => 1));
		$success = !empty($ret['ok']) && empty($ret['updatedExisting']);
		if (!$success) {
			Billrun_Factory::log("Couldn't update log record : " . print_r($update, 1), Zend_Log::ERR);
		}
	}

	protected function getReleaseQuery() {
		return array(
			'action' => 'send_file',
			'filtration' => 'send_' . $this->export_name,
			'end_time' => array('$exists' => false)
		);
	}

	protected function getInsertData() {
		return array(
			'action' => 'send_file',
			'filtration' => 'send_' . $this->export_name
		);
	}

	protected function getConflictingQuery() {
		return array('filtration' => 'send_' . $this->export_name);
	}

}
