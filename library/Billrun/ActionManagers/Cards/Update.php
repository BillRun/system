<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is an update parser for the use of Cards Update action.
 *
 */
class Billrun_ActionManagers_Cards_Update extends Billrun_ActionManagers_Cards_Action {

	use Billrun_FieldValidator_ServiceProvider;

	/**
	 * Field to hold the data to be written in the DB.
	 * @var type Array
	 */
	protected $query = array();
	protected $update = array();
	protected $validateQuery = array();
	protected $serialRange;

	/**
	 * Get the array of fields to be set in the query record from the user input.
	 * @return array - Array of fields to set.
	 */
	protected function getQueryFields() {
		return Billrun_Factory::config()->getConfigValue('cards.query_fields', array());
	}

	/**
	 * Get the array of fields to be set in the update record from the user input.
	 * @return array - Array of fields to set.
	 */
	protected function getUpdateFields() {
		return Billrun_Factory::config()->getConfigValue('cards.update_fields', array());
	}

	/**
	 * Get the array of valid statuses.
	 * @return array - Array of valid statuses.
	 */
	protected function getStatuses() {
		return Billrun_Factory::config()->getConfigValue('cards.status', array());
	}

	/**
	 * Get the indication whether to validate status transition or not.
	 * @return bool - TRUE or FALSE (default: FALSE).
	 */
	protected function getStatusValidation() {
		return Billrun_Factory::config()->getConfigValue('cards.statusValidation', FALSE);
	}

	/**
	 * Get the array of initial statuses.
	 * @return array - Array of initial statuses.
	 */
	protected function getInitialStatus() {
		return Billrun_Factory::config()->getConfigValue('cards.initialStatus', array());
	}

	/**
	 * Get the arrays of statuses allowed for each requested status (status transition permit).
	 * @return array - Array of statuses allowed for each requested status.
	 */
	protected function getAllowFromStatus() {
		return Billrun_Factory::config()->getConfigValue('cards.allowFromStatus', array());
	}

	/**
	 * This function validates transition between statuses.
	 * @param array $input - fields for query and update. 
	 * @return Return false (and writes errLog) when find 
	 * mismatch (not permited transit action between statuses)
	 * and true when validation check OK and can continue 
	 * with the process.
	 */
	protected function statusValidationProcess() {
		$statusTransition = FALSE;
		$this->validateQuery = $this->query;
		$statusValidation = $this->getStatusValidation();
		$availableStatuses = $this->getStatuses();
		// Check if to validate status
		if ($statusValidation) {
			// Check request for status change
			if (isset($this->update['status'])) {
				$updateStatus = $this->update['status'];
				// Check requested status in the permissible statuses available array
				if (!in_array($updateStatus, $availableStatuses)) {
					$errorCode =  37;
					$this->reportError($errorCode, Zend_Log::NOTICE, array($updateStatus));
					return false;
				}
				$allowFromStatus = $this->getAllowFromStatus();
				//Check existance of original status in the request for status change
				if (isset($this->query['status'])) {
					$queryStatus = $this->query['status'];
					$this->validateQuery['status'] = ['$ne' => $queryStatus];
					if (!in_array($queryStatus, $allowFromStatus[$updateStatus])) {
						$errorCode =  38;
						$this->reportError($errorCode, Zend_Log::NOTICE, array($queryStatus, $updateStatus));
						return false;
					}
					// Check if there are origin available permissible statuses 
				} else if ($allowFromStatus[$updateStatus]) {
					$this->validateQuery['status']['$nin'] = $allowFromStatus[$updateStatus];
				} else {
					$errorCode =  39;
					$this->reportError($errorCode, Zend_Log::NOTICE, array($updateStatus));
					return false;
				}
				// Check serial_number range validity
				if (isset($this->query['serial_number'])) {
					if (is_array($this->query['serial_number'])) {
						if (isset($this->query['serial_number']['$gte']) xor isset($this->query['serial_number']['$lte'])) {
							$errorCode =  40;
							$this->reportError($errorCode, Zend_Log::NOTICE);
							return false;
						}
					}
				}
				// Check if there are impermissible statuses for the requested new status in query from the DB 
				$count = $this->collection->query($this->validateQuery)->cursor()->count();
				if ($count) {
					$errorCode =  41;
					$this->reportError($errorCode, Zend_Log::NOTICE, array($count, implode(', ', array_diff($availableStatuses, $allowFromStatus[$updateStatus])), $updateStatus));
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * This function builds the query for the Cards Update API after 
	 * validating existance of mandatory fields and their values.
	 * @param array $input - fields for query in Jason format. 
	 * @return Return false (and writes errLog) when fails to loocate 
	 * all needed fields and/or values for query and true when success.
	 */
	protected function queryProcess($input) {
		$queryFields = $this->getQueryFields();

		$jsonQueryData = null;
		$query = $input->get('query');
		if (empty($query) || (!($jsonQueryData = json_decode($query, true)))) {
			$errorCode =  30;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		$errLog = array_diff($queryFields, array_keys($jsonQueryData));

		if (!isset($jsonQueryData['batch_number']) || !isset($jsonQueryData['serial_number'])) {
			$errorCode =  31;
			$missingQueryFields = implode(', ', $errLog);
			$this->reportError($errorCode, Zend_Log::NOTICE, array($missingQueryFields));
			return false;
		}

		if (isset($jsonQueryData['secret'])) {
			$jsonQueryData['secret'] = hash('sha512', $jsonQueryData['secret']);
		}

		$this->query = array();
		foreach ($queryFields as $field) {
			if (isset($jsonQueryData[$field])) {
				$this->query[$field] = $jsonQueryData[$field];
			}
		}

		return true;
	}

	/**
	 * This function builds the update for the Cards Update API after 
	 * validating existance of field and that they are not empty.
	 * @param array $input - fields for update in Jason format. 
	 * @return Return false (and writes errLog) when fails to loocate 
	 * all needed field and/or values for query and true when success.
	 */
	protected function updateProcess($input) {
		$updateFields = $this->getUpdateFields();

		$jsonUpdateData = null;
		$update = $input->get('update');
		if (empty($update) || (!($jsonUpdateData = json_decode($update, true)))) {
			$errorCode =  32;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		foreach ($updateFields as $field) {
			if (isset($jsonUpdateData[$field]) && !empty($jsonUpdateData[$field])) {
				$this->update[$field] = $jsonUpdateData[$field];
			}
		}

		// service provider validity check
		if (!$this->validateServiceProvider($this->update['service_provider'])) {
			$errorCode =  36;
			$this->reportError($errorCode, Zend_Log::NOTICE, array($this->update['service_provider']));
			return false;
		}

		if (isset($this->update['to'])) {
			$this->update['to'] = new Mongodloid_Date(strtotime($this->update['to']));
		}

		return true;
	}

	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		$exception = null;
		try {
			$updateResult = $this->collection->update($this->query, array('$set' => $this->update), array('multiple' => true));
			$count = $updateResult['nModified'];
			$found = $updateResult['n'];
		} catch (\MongoException $e) {
			$exception = $e;
			$errorCode =  33;
			$this->reportError($errorCode, Zend_Log::NOTICE);
		}

		if (!$count) {
			if ($found) {
				$errorCode =  35;
			} else {
				$errorCode =  34;
			}
			$this->reportError($errorCode, Zend_Log::NOTICE);
		}

		$outputResult = array(
			'status' => 1,
			'desc' => "Success updating cards",
			'details' => 'Updated ' . $count . ' card(s)'
		);

		return $outputResult;
	}

	/**
	 * Parse the received request.
	 * @param type $input - Input received.
	 * @return true if valid.
	 */
	public function parse($input) {

		if (!$this->queryProcess($input)) {
			return false;
		}

		if (!$this->updateProcess($input)) {
			return false;
		}

		if (!$this->statusValidationProcess()) {
			return false;
		}

		return true;
	}

}
