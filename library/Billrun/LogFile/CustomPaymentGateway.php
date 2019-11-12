<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing custom payment gateway log file
 *
 * @package  LogFile
 * @since    5.10
 */
class Billrun_LogFile_CustomPaymentGateway extends Billrun_LogFile {

	/**
	 * source field of the log file
	 * @var string
	 */
	protected $source;
	protected $orphanTime = '1 hour ago';

	public function __construct($options = array()) {
		if (empty($options['source'])) {
			throw new Exception('Missing source');
		}
		$this->source = $options['source'];
		parent::__construct($options);
		$this->collection = Billrun_Factory::db()->logCollection();
		$key = $this->generateKeyFromOptions($options);
		$query = array(
			'source' => $this->source,
			'key' => $key,
			'process_time' => array('$gt' => new MongoDate(strtotime($this->orphanTime))),
		);

		$customLog = $this->collection->query($query)->cursor();
		if ($customLog->count() > 1) {
			throw new Exception('Billrun_LogFile_CustomPaymentGateway: More than one log file was found');
		} elseif ($customLog->count() == 1) {
			$this->data = $customLog->current();
			if (isset($this->data['process_time'])) {
				throw new Exception('Billrun_LogFile_CustomPaymentGateway: file already created');
			}
			$this->setStartProcessTime();
			$this->data->collection($this->collection);
		} else {
			$this->data = new Mongodloid_Entity();
			$this->data->collection($this->collection);
			$this->data['key'] = $key;
			$this->data['source'] = $this->source;
                        $this->data['errors'] = [];
                        $this->data['warnings'] = [];
                        $this->data['info'] = [];
			$this->setStartProcessTime();
			$this->save();
		}
	}

	public function setSequenceNumber() {
		return $this->data->createAutoInc('seq');
	}

	public function getSequenceNumber() {
		if (isset($this->data['seq'])) {
			return $this->data['seq'];
		}
		return NULL;
	}

	protected function generateKeyFromOptions($options) {
		return md5(serialize($options));
	}

	public function getStamp() {
		if (isset($this->data['stamp'])) {
			return $this->data['stamp'];
		}
		return NULL;
	}
        
        public function updateLogFileField($field_name, $value) {
            if($field_name === "errors" || $field_name === "warnings" || $field_name === "info"){
                $array = $this->data[$field_name];
                array_push($array, $value);
                $this->data[$field_name] = $array;
                $this->save();
            }else{
                $this->data[$field_name] = $value;
                $this->save();
            }
        }
}
