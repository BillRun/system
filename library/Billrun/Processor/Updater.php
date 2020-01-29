<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This defines an empty processor that pass the processing action to external plugin.
 */
abstract class Billrun_Processor_Updater extends Billrun_Processor {

	static protected $type = 'updater';
	
	/**
	 * Number of good lines
	 * @var int
	 */
	protected $goodLines = 0;

	public function __construct($options = array()) {
		parent::__construct($options);
		if ($this->getType() == 'updater') {
			throw new Exception('Billrun_Processor_Updater::__construct : cannot run without specifing a specific type.');
		}
	}

	protected function processFinished() {
		return Billrun_Factory::chain()->trigger('isProcessingFinished', array($this->getType(), $this->fileHandler, &$this));
	}

	/**
	 * @see Billrun_Processor::getSequenceData
	 */
	public function getFilenameData($filename) {
		return Billrun_Factory::chain()->trigger('getFilenameData', array($this->getType(), $filename, &$this));
	}

	/**
	 * method to process file by the processor parser
	 * 
	 * @return mixed
	 */
	public function process() {
		Billrun_Factory::dispatcher()->trigger('beforeProcessorParsing', array($this));

		if ($this->processLines() === FALSE) {
			Billrun_Factory::log("Billrun_Processor: cannot parse " . $this->filePath, Zend_Log::ERR);
			return FALSE;
		}

		if ($this->updateData() === FALSE) {
			Billrun_Factory::log()->log("Billrun_Processor: cannot update by parser lines " . $this->filePath, Zend_Log::ERR);
			return FALSE;
		}
		if ($this->logDB() === FALSE) {
			Billrun_Factory::log()->log("Billrun_Processor: cannot log parsing action" . $this->filePath, Zend_Log::WARN);
			return FALSE;
		}
		if ($this->getType() == 'transactions_response') {
			$this->updateLeftPaymentsByFileStatus();
		}

		$this->removefromWorkspace($this->getFileStamp());
		Billrun_Factory::dispatcher()->trigger('afterProcessorRemove', array($this));
		return count($this->data['data']);
	}


	/**
	 * This function should be used to build a Data row
	 * @param $data the raw row data
	 * @return Array that conatins all the parsed and processed data.
	 */
	public function buildDataRow() {
		$row['source'] = self::$type;
		$row['file'] = basename($this->filePath);
		$row['log_stamp'] = $this->getFileStamp();
		$row['process_time'] = new MongoDate();
		return $row;
	}
	
	protected function logDB() {

		$log = Billrun_Factory::db()->logCollection();

		$header = array();
		if (isset($this->data['header'])) {
			$header = $this->data['header'];
		}

		$trailer = array();
		if (isset($this->data['trailer'])) {
			$trailer = $this->data['trailer'];
		}


		$header['linesStats']['good'] = $this->goodLines;

		$current_stamp = $this->getStamp(); // mongo id in new version; else string
		if ($current_stamp instanceof Mongodloid_Entity || $current_stamp instanceof Mongodloid_Id) {
			$resource = $log->findOne($current_stamp);
			if (!empty($header)) {
				$resource->set('header', $header, true);
			}
			if (!empty($trailer)) {
				$resource->set('trailer', $trailer, true);
			}
			$resource->set('process_hostname', Billrun_Util::getHostName(), true);
			$resource->set('process_time', new MongoDate(), true);
			return $log->save($resource);
		} else {
			// backward compatibility
			// old method of processing => receiver did not logged, so it's the first time the file logged into DB
			$entity = new Mongodloid_Entity($trailer);
			if ($log->query('stamp', $entity->get('stamp'))->count() > 0) {
				Billrun_Factory::log()->log("Billrun_Processor::logDB - DUPLICATE! trying to insert duplicate log file with stamp of : {$entity->get('stamp')}", Zend_Log::NOTICE);
				return FALSE;
			}
			return $entity->save($log);
		}
	}
	
	public function incrementGoodLinesCounter() {
		$this->goodLines++;
	}
	

}