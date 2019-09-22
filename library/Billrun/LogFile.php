<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * BillRun LogFile class
 *
 * @package  Billrun
 * @since    1
 */
class Billrun_LogFile {

	/**
	 * the data container
	 * @var Mongodloid_Entity
	 */
	protected $data;

	/**
	 * 
	 * @param type $options
	 */
	public function __construct($options = array()) {
		$this->data = new Mongodloid_Entity();
		$this->data->collection(Billrun_Factory::db()->logCollection());
	}

	public function setFileName($filename, $immediate = false) {
		if ($immediate) {
			$this->data->set('file_name', $filename);
		} else {
			$this->data['file_name'] = $filename;
		}
	}

	/**
	 * Save the billrun to the db
	 * @param type $param
	 * @return type
	 */
	public function save() {
		if (isset($this->data)) {
			try {
				$this->data->save(NULL, 1);
				return true;
			} catch (Exception $ex) {
				Billrun_Factory::log()->log('Error saving billrun document. Error code: ' . $ex->getCode() . '. Message: ' . $ex->getMessage(), Zend_Log::ERR);
			}
		}
		return false;
	}

	public function setStartProcessTime($time = null) {
		if (is_null($time)) {
			$time = time();
		}
		$this->data['start_process_time'] = new MongoDate($time);
	}

	public function setProcessTime($time = null) {
		if (is_null($time)) {
			$time = time();
		}
		$this->data['process_time'] = new MongoDate($time);
	}
	
	public function setStamp() {
		$newLog['key'] = $this->data['key'];
		$newLog['source'] = $this->data['source'];
		$newLog['start_process_time'] = $this->data['start_process_time'];
		$this->data['stamp'] = md5(serialize($newLog));
	}
	
	public function setSource($source) {
		$this->data['source'] = $source;
	}
}