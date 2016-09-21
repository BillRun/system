
<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a parser to be used by the services action.
 *
 */
class Billrun_ActionManagers_Services_Update extends Billrun_ActionManagers_Services_Action {
	
	/**
	 * Field to hold the data to be written in the DB.
	 * @var type Array
	 */
	protected $query = array();
	protected $update = array();
	
	protected $time;

	/**
	 */
	public function __construct() {
		parent::__construct(array('error' => "Success creating service"));
	}

	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		try {
			$this->collection->update($this->query, array('$set' => $this->update));
		} catch (\Exception $e) {
			$errorCode = Billrun_Factory::config()->getConfigValue("services_error_base") + 1;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			Billrun_Factory::log($e->getCode() . ": " . $e->getMessage(), Billrun_Log::WARN);
		}

		$outputResult = array(
			'status' => $this->errorCode == 0 ? 1 : 0,
			'desc' => $this->error,
			'error_code' => $this->errorCode,
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

		$update = $input->get('update');
		if (empty($update) || (!($jsonData = json_decode($update, true))) || !$this->setUpdateFields($jsonData)) {
			$errorCode = Billrun_Factory::config()->getConfigValue("services_error_base") + 2;
			$this->reportError($errorCode, Zend_Log::NOTICE);
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
		$this->query = Billrun_Util::getDateBoundQuery();
		
		$fields = Billrun_Factory::config()->getConfigValue('services.fields');
		
		// Array of errors to report if any error occurs.
		$invalidFields = array();

		// Get only the values to be set in the update record.
		foreach ($fields as $field) {
			if(!isset($field['mandatory']) || !$field['mandatory']) {
				continue;
			}
			
			$fieldName = $field['field_name'];
			
			if (!isset($queryData[$fieldName]) || empty($queryData[$fieldName])) {
				$invalidFields[] = $fieldName;
			} else if (isset($queryData[$fieldName])) {
				$this->query[$fieldName] = $queryData[$fieldName];
			}
		}

		return $invalidFields;
	}
	
	/**
	 * Set all the update fields in the record with values.
	 * @param array $updateData - Data received.
	 * @return bool
	 */
	protected function setUpdateFields($updateData) {
		$fields = Billrun_Factory::config()->getConfigValue('services.fields');
				
		// Get only the values to be set in the update record.
		foreach ($fields as $field) {
			$fieldName = $field['field_name'];
			if (isset($field['editable']) && !$field['editable']) {
				continue;
			}
			if (isset($updateData[$fieldName])) {
				$this->update[$fieldName] = $updateData[$fieldName];
			}
		}
		
		return true;
	}
}
