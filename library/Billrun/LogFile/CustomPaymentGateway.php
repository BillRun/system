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
		$stamp = $this->generateStampFromOptions($options);
		$query = array(
			'source' => $this->source,
			'stamp' => $stamp,
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
			$this->data->collection($this->collection);
		} else {
			$this->data = new Mongodloid_Entity();
			$this->data->collection($this->collection);
			$this->data['creation_time'] = new MongoDate();
			$this->data['stamp'] = $stamp;
			$this->data['source'] = $this->source;
                        $this->data['errors'] = [];
                        $this->data['warnings'] = [];
                        $this->data['info'] = [];
			$this->data['rand'] = Billrun_Util::generateRandomNum();
			$this->setStamp();
			$this->save();
		}
	}

	public function setSequenceNumber() {
		Billrun_Factory::log("Setting log file's sequence number", Zend_Log::DEBUG);
		return $this->data->createAutoInc('seq');
	}

	public function getSequenceNumber() {
		if (isset($this->data['seq'])) {
			return $this->data['seq'];
		}
		return NULL;
	}

	protected function generateStampFromOptions($options) {
		return md5(serialize($options));
	}

	public function getStamp() {
		if (isset($this->data['stamp'])) {
			return $this->data['stamp'];
		}
		return NULL;
	}
        
	/**
	 * Function to add field/fields to the log file
	 * @param string $field_name - comes with "$value" - value's field name
	 * @param string $value - comes with "$field_name" - field's value
	 * @param array $fields - array of field_name => value - will come without "$field_name"/"$value"
	 */
    public function updateLogFileField($field_name = null, $value = null, $fields = []) {
		if (!empty($field_name) && !empty($value) && empty($fields)) {
			$fields = array($field_name => $value);
		}
		foreach($fields as $field_name => $value) {
            if(in_array($field_name, ['errors', 'warnings', 'info'])){
                $array = $this->data[$field_name];
                array_push($array, $value);
                $this->data[$field_name] = $array;
            }else{
                $this->data[$field_name] = $value;
            }
            }
        }
        
	public function getLogFileFieldValue($field_name, $defaultValue = null) {
		return isset($this->data[$field_name]) ? $this->data[$field_name] : $defaultValue;
        }
        
        public function saveLogFileFields(){
            $this->data->save();
        }
}
