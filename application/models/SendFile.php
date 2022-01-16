<?php
/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2022 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Send file model class to pull file' record, and send it to configured location
 *
 * @package  Models
 * @subpackage 
 * @since    5.14
 */
class SendFileModel {

	/**
	 * Sender type
	 * @var string 
	 */
	public $type = "";
	
	/**
	 * Sender type configuration
	 * @var array
	 */
	protected $type_config = "";
	
	/**
	 * Specific sender name, under the chosen file type
	 * @var string 
	 */
	public $name = "";
	
	/**
	 * Specific file to send
	 * @var string
	 */
	public $file_name = "";
	
	/**
	 * File type connections array
	 * @var array
	 */
	public $connections = [];
	
	/**
	 * Relevant log documents
	 * @var array
	 */
	public $log_documents = [];
	
	public function __construct($options) {
		$this->type = isset($options['type']) ? $options['type'] : $this->type;
		$this->name = isset($options['name']) ? $options['name'] : $this->name;
		$this->file_name = isset($options['file_name']) ? $options['file_name'] : "";
		$this->type_config = Billrun_Factory::config()->getConfigValue($this->type, []);
	}
	
	public function getSenderDetails() {
		if (empty($this->type) || empty($this->name)) {
			Billrun_Factory::log("Missing type/name in the send file command..", Zend_Log::ERR);
			return false;
		}
		if (empty($this->type_config)) {
			Billrun_Factory::log('Didn\'t find configuration type : ' . $this->type, Zend_Log::ERR);
			return false;
		}
		Billrun_Factory::log("Pulled " . $this->type . " configuration..", Zend_Log::DEBUG);
		$name = $this->name;
		$file_type = current(array_filter($this->type_config, function($file_type) use($name) {
				return $file_type['name'] == $name;
			}));
		Billrun_Factory::log("Pulled " . $this->name . " file type configuration..", Zend_Log::DEBUG);
		Billrun_Factory::log("Loading file type connections..", Zend_Log::DEBUG);
		$this->connections = $this->getConfiguredConnections($file_type);
		Billrun_Factory::log("Loading relevant log documents..", Zend_Log::DEBUG);
		$this->log_documents = $this->getRelevantFilesLog($file_type);
		return (!empty($this->connections) && !empty($this->log_documents));
	}

	public function getConfiguredConnections($file_type) {
		return !empty($file_type['senders']['connections']) ? $file_type['senders']['connections'] : [];
	}

	public function getRelevantFilesLog() {
		$orphan_time = Billrun_Util::getIn($file_type['generator'], 'orphan_files_time', '6 hours');
		$query = [
			'source' => ($this->type == 'export_generators') ? 'export' : $this->type,
			'name' => $this->name,
			'$and' => array(
				array('export_start_time' => array('$lt' => new MongoDate(strtotime('-' . $orphan_time)))),
				array('exported_time' => array('$exists' => false)),
			)
		];
		if (!empty($this->file_name)) {
			$query['file_name'] = $this->file_name;
		}
		$relevant_files = Billrun_Factory::db()->logCollection()->query($query)->cursor();
		if (count($relevant_files) == 0) {
			$this->_controller->addOutput("No files to send");
			return false;
		}
		Billrun_Factory::log("Found " . count($relevant_files) . " files to send..", Zend_Log::DEBUG);
		return $relevant_files;
	}

	public function updateDbLogRecord($file_log) {
		$update = ['exported_time' => new MongoDate()];
		$ret = Billrun_Factory::db()->logCollection()->update(array('stamp' => $file_log['stamp']), array('$set' => $update), array('w' => 1));
		$success = !empty($ret['ok']) && $ret['updatedExisting'];
		if (!$success) {
			Billrun_Factory::log("Couldn't update log record : " . print_r($update, 1), Zend_Log::ERR);
		}
	}
}

