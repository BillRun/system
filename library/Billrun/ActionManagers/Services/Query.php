<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a parser to be used by the services action.
 *
 * @todo This class is very similar to balances query, 
 * a generic query class should be created for both to implement.
 */
class Billrun_ActionManagers_Services_Query extends Billrun_ActionManagers_Services_Action {

	/**
	 * Field to hold the data to be written in the DB.
	 * @var type Array
	 */
	protected $serviceQuery = array();

	/**
	 */
	public function __construct() {
		parent::__construct(array('error' => "Success querying service"));
	}

	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		$service = $this->collection->query($this->serviceQuery)->cursor()->current();
		
		// Check if the return data is invalid.
		if ($service && !$service->isEmpty()) {
			$returnData = $service->getRawData();
		} else {
			$returnData = array();
			$this->reportError(Billrun_Factory::config()->getConfigValue("services_error_base") + 23);
		}

		$outputResult = array(
			'status' => $this->errorCode == 0 ? 1 : 0,
			'desc' => $this->error,
			'error_code' => $this->errorCode,
			'details' => $returnData 
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
			$errorCode = Billrun_Factory::config()->getConfigValue("services_error_base") + 21;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		$invalidFields = $this->setQueryFields($jsonData);

		// If there were errors.
		if (empty($this->serviceQuery)) {
			$errorCode = Billrun_Factory::config()->getConfigValue("services_error_base") + 22;
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
		$queryFields = $this->getQueryFields();

		// Arrary of errors to report if any occurs.
		$invalidFields = array();

		// Get only the values to be set in the update record.
		foreach ($queryFields as $field) {
			$fieldName = $field['field_name'];
			if (isset($queryData[$fieldName]) && !empty($queryData[$fieldName])) {
				$this->serviceQuery[$fieldName] = $queryData[$fieldName];
			} else {
				$invalidFields[] = $fieldName;
			}
		}

		return $invalidFields;
	}

}
