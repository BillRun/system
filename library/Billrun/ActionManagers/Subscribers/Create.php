<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a parser to be used by the subscribers action.
 *
 */
class Billrun_ActionManagers_Subscribers_Create extends Billrun_ActionManagers_Subscribers_Action {

	use Billrun_ActionManagers_Subscribers_Validator {
		validate as baseValidate;
	}
	use Billrun_ActionManagers_Subscribers_Servicehandler;
	use Billrun_Traits_FieldValidator;
	
	/**
	 * Field to hold the data to be written in the DB.
	 * @var type Array
	 */
	protected $query = array();

	/**
	 * Get the query to run to get a subscriber from the db.
	 * @return array Query to run in the mongo.
	 */
	protected function getSubscriberQuery() {
		$subscriberQuery = array_merge(Billrun_Utils_Mongo::getDateBoundQuery(), array('type' => $this->type));
		//TODO: Create a 'fields collection', this will help us prevent explicit loops.
		foreach ($this->fields as $field) {
			$fieldName = $field['field_name'];
			// If the field is not unique, or was not supplied in the query at all,
			// skip it.
			if (empty($field['unique']) || empty($this->query[$fieldName])) {
				continue;
			}
			if (is_array($this->query[$fieldName])) {
				$subscriberQuery['$or'][][$fieldName] = array('$in' => Billrun_Util::array_remove_compound_elements($this->query[$fieldName]));
			}
			$subscriberQuery['$or'][][$fieldName] = $this->query[$fieldName];
		}
		return $subscriberQuery;
	}

	/**
	 * Return the base query of the action.
	 * This is used to validate the uniqeness of sensitive input values.
	 * @return array
	 * @note '_getBaseQuery' is a function of the Billrun_Traits_FieldValidator trait, 
	 * its default implementation is empty.
	 * It's named with an underscore to avoid a clash between another getBaseQuery function.
	 */
	protected function _getBaseQuery() {
		$query = Billrun_Utils_Mongo::getDateBoundQuery();
		$query['type'] = $this->type;
		return $query;
	}
	
	/**
	 * Check if the subscriber to create already exists.
	 * @return boolean - true if the subscriber exists.
	 */
	protected function subscriberExists() {
		// Check if the subscriber already exists.
		$subscriberQuery = $this->getSubscriberQuery();

		$subscribers = $this->collection->query($subscriberQuery);
		
		// TODO: Use the subscriber class.
		if ($subscribers->count() > 0) {
			$errorCode = 0;
			$parameters = http_build_query($this->query, '', ', ');
			$this->reportError($errorCode, Zend_Log::NOTICE, array($parameters));
			return true;
		}

		return false;
	}

	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		try {
			if (!$this->subscriberExists()) {
				$entity = new Mongodloid_Entity($this->query);
				$this->collection->save($entity, 1);
			}
		} catch (\MongoException $e) {
			$errorCode =  1;
			Billrun_Factory::log($e->getCode() . ": " . $e->getMessage(), Billrun_Log::WARN);
			$this->reportError($errorCode, Zend_Log::NOTICE);
		}

		$outputResult = array(
			'status' => 1,
			'desc' => "Success creating subscriber",
		);

		if (isset($entity)) {
			$outputResult['details'] = $entity->getRawData();
		}
		return $outputResult;
	}

	/**
	 * Parse the received request.
	 * @param type $input - Input received.
	 * @return true if valid.
	 */
	public function parse($input) {
		if (!parent::parse($input) || !$this->setQueryRecord($input)) {
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
		$query = $input->get('subscriber');
		if (empty($query) || (!($jsonData = json_decode($query, true)))) {
			$errorCode =  2;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		// Validate the fields.
		$this->enforce($this->fields, $jsonData);
		
		$invalidFields = $this->setQueryFields($jsonData);

		// If there were errors.
		if (!empty($invalidFields)) {
			// Create an exception.
			Billrun_Factory::log("Invalid fields: " . print_r($invalidFields,1));
			throw new Billrun_Exceptions_InvalidFields($invalidFields);
		}

		$this->setAdditionalFields();

		return $this->validate();
	}

	protected function setGeneratedFields() {
		foreach ($this->fields as $field) {
			$fieldName = $field['field_name'];
			if (isset($field['generated']) && $field['generated']) {
				$this->query[$fieldName] = Billrun_Factory::db()->subscribersCollection()->createAutoInc();
			}
		}
	}

	protected function setAdditionalFields() {
		$this->query['type'] = $this->type;
		// Set the from and to values.
		$this->query['from'] = new Mongodloid_Date();
		$this->query['plan_activation'] = new Mongodloid_Date();
		$this->query['to'] = new Mongodloid_Date(strtotime('+100 years'));

		$this->setGeneratedFields();
	}

	/**
	 * Set all the query fields in the record with values.
	 * @param array $queryData - Data received.
	 * @return array - Array of strings of invalid field name. Empty if all is valid.
	 */
	protected function setQueryFields($queryData) {
		// Array of errors to report if any error occurs.
		$invalidFields = array();

		// Get only the values to be set in the update record.
		foreach ($this->fields as $field) {
			$fieldName = $field['field_name'];
			if ((isset($field['mandatory']) && $field['mandatory']) &&
				(!isset($queryData[$fieldName]) || empty($queryData[$fieldName]))) {
				$invalidFields[] = new Billrun_DataTypes_InvalidField($fieldName);
				continue;
			} else if (!isset($queryData[$fieldName])) {
				continue;
			}
			
			// TODO: Create some sort of polymorphic behaviour to correctly handle
			// the updating fields.
			if($fieldName === 'services') {
				$toSet = $this->getSubscriberServices($queryData['services'], new Mongodloid_Date(), new Mongodloid_Date(strtotime('+100 years')));
			} else {
				$toSet = $queryData[$fieldName];
			}
			
			if(empty($toSet)) {
				continue;
			}
			
			$this->query[$fieldName] = $toSet;
		}

		return $invalidFields;
	}

	protected function validate() {
		// Validate the input.
		if (($this->type === 'subscriber') && (!$this->isAccountExists($this->query['aid']))) {
				return false;
		}
		
		return $this->baseValidate();
	}

	protected function isAccountExists($aid) {
		$query = array_merge(
			Billrun_Utils_Mongo::getDateBoundQuery(), 
			array("type" => "account", "aid" => $aid)
		);
		if (Billrun_Factory::db()->subscribersCollection()->query($query)->cursor()->count() === 0) {
			$errorCode =  8;
			$this->reportError($errorCode, Zend_Log::NOTICE, array($aid));
			return false;
		}
		return true;
	}

	protected function getSubscriberData() {
		return $this->query;
	}

	/**
	 * Return the collection instance.
	 * This is used to validate the uniqeness of sensitive input values.
	 * @return Mongodloid_Collection 
	 * @note '_getCollection' is an abstract function of the trait Billrun_Traits_FieldValidator.
	 * It's named with an underscore to avoid a clash between another getCollection function.
	 */
	protected function _getCollection() {
		return Billrun_Factory::db()->subscribersCollection();
	}

}
