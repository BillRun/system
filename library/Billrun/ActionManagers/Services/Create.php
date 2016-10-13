<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Parser to be used by the services action
 *
 * @package  services
 * @since    5.1
 */
class Billrun_ActionManagers_Services_Create extends Billrun_ActionManagers_Services_Action {
	
	/**
	 * Field to hold the data to be written in the DB.
	 * @var type Array
	 */
	protected $query = array();
	
	/**
	 * Get the array of fields to be inserted in the create record from the user input.
	 * @return array - Array of fields to be inserted.
	 */
	protected function getCreateFields() {
		return Billrun_Factory::config()->getConfigValue('services.create_fields', array());
	}
	
	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		$details = array();
		try {
			if (!$this->serviceExists()) {
				$entity = new Mongodloid_Entity($this->query);
				$this->collection->save($entity, 1);
				$details = $entity->getRawData();
			}
		} catch (\MongoException $e) {
			$errorCode =  1;
			Billrun_Factory::log($e->getCode() . ": " . $e->getMessage(), Billrun_Log::WARN);
			$this->reportError($errorCode, Zend_Log::NOTICE);
		}

		$outputResult = array(
			'status' => 1,
			'desc' => "Success creating service",
			'details' => $details
		);
		return $outputResult;
	}

	/**
	 * Parse the received request.
	 * @param type $input - Input received.
	 * @return true if valid.
	 */
	public function parse($input) {
		if (!$this->setQueryRecord($input)) {
			return false;
		}

		return true;
	}

	/**
	 * Set the values for the query record to be set.
	 * @param httpRequest $input - The input received from the user.
	 * @return true if successful false otherwise.
	 */
	protected function setQueryRecord($input) {
		$jsonData = null;
		$query = $input->get('query');
		if (empty($query) || (!($jsonData = json_decode($query, true)))) {
			$this->reportError(2, Zend_Log::NOTICE);
			return false;
		}

		$invalidFields = $this->setQueryFields($jsonData);

		// If there were errors.
		if (!empty($invalidFields)) {
			// Create an exception.
			throw new Billrun_Exceptions_InvalidFields($invalidFields);
		}
		
		return true;
	}
	
	/**
	 * Set all the query fields in the record with values.
	 * @param array $queryData - Data received.
	 * @return array - Array of strings of invalid field name. Empty if all is valid.
	 */
	protected function setQueryFields($queryData) {
		// Array of errors to report if any error occurs.
		$invalidFields = array();

		$fields = Billrun_Factory::config()->getConfigValue('services.fields');
		
		// Get only the values to be set in the update record.
		foreach ($fields as $field) {
			$fieldName = $field['field_name'];
			if ((isset($field['mandatory']) && $field['mandatory']) &&
				(!isset($queryData[$fieldName]) || empty($queryData[$fieldName]))) {				
				$invalidFields[] = new Billrun_DataTypes_InvalidField($fieldName);
			} else if (isset($queryData[$fieldName])) {
				$type = (isset($field['type'])) ? ($field['type']) : (null);
				$this->setField($queryData[$fieldName], $fieldName, $type);
			}
		}

		return $invalidFields;
	}
	
	/**
	 * TODO: Use the translators when it is merged
	 * @param array $field
	 */
	protected function setField($data, $fieldName, $type) {
		if(!$type) {
			$this->query[$fieldName] = $data;
			return;
		}
		
		// Translate by type.
		if($type == 'date') {
			$date = strtotime($data);
			$newData = new MongoDate($date);
			$this->setField($newData, $fieldName, null);
		} else {
			throw new Exception("Invalid field type");
		}
	}
	
	/**
	 * Check if the subscriber to create already exists.
	 * @return boolean - true if the subscriber exists.
	 */
	protected function serviceExists() {
		// Check if the subscriber already exists.
		$serviceQuery = array('name' => $this->query['name']);

		$service = $this->collection->query($serviceQuery);

		if ($service->count() > 0) {
			$this->reportError(0, Zend_Log::NOTICE, array($this->query['name']));
			return true;
		}

		return false;
	}
}
