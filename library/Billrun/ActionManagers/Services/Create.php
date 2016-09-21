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
	 */
	public function __construct() {
		parent::__construct(array('error' => "Success creating service"));
	}

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
		$exception = null;
		try {
			if (!$this->serviceExists()) {
				$entity = new Mongodloid_Entity($this->query);
				$this->collection->save($entity, 1);
			}
		} catch (\Exception $e) {
			$exception = $e;
			$errorCode = Billrun_Factory::config()->getConfigValue("services_error_base") + 1;
			$this->reportError($errorCode, Zend_Log::NOTICE);
		}

		$outputResult = array(
			'status' => $this->errorCode == 0 ? 1 : 0,
			'desc' => $this->error,
			'error_code' => $this->errorCode,
			'details' => (!$this->errorCode) ?
				('Service added') :
				('Fail to add service')
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
		$query = $input->get('service');
		if (empty($query) || (!($jsonData = json_decode($query, true)))) {
			$errorCode = Billrun_Factory::config()->getConfigValue("services_error_base") + 2;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		$invalidFields = $this->setQueryFields($jsonData);

		// If there were errors.
		if (!empty($invalidFields)) {
			$errorCode = Billrun_Factory::config()->getConfigValue("services_error_base") + 3;
			$this->reportError($errorCode, Zend_Log::NOTICE, array(implode(',', $invalidFields)));
			return false;
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
				$invalidFields[] = $fieldName;
			} else if (isset($queryData[$fieldName])) {
				$this->query[$fieldName] = $queryData[$fieldName];
			}
		}

		return $invalidFields;
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
			$errorCode = Billrun_Factory::config()->getConfigValue("services_error_base");
			$this->reportError($errorCode, Zend_Log::NOTICE, array($this->query['name']));
			return true;
		}

		return false;
	}
}
