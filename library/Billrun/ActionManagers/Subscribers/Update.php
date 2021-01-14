
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
class Billrun_ActionManagers_Subscribers_Update extends Billrun_ActionManagers_Subscribers_Action {
	use Billrun_ActionManagers_Subscribers_Servicehandler;
	use Billrun_ActionManagers_Subscribers_Validator {
		validateOverlap as baseValidateOverlap;
	}
	use Billrun_Traits_FieldValidator;
	
	/**
	 * Field to hold the data to be written in the DB.
	 * @var type Array
	 */
	protected $query = array();
	protected $update = array();
	
	/**
	 *
	 * @var Mongodloid_Entity
	 */
	protected $oldEntity = array();
	protected $time;

	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		try {
			$this->time = new Mongodloid_Date();
			if (!$oldEntity = $this->getOldEntity()) {
				return false;
			}
			$newEntity = $this->updateEntity($this->oldEntity); // saves the new entity
				
			// Check if changed plans.
			if($newEntity['plan'] !== $this->oldEntity['plan']) {
				$this->oldEntity['plan_deactivation'] = new Mongodloid_Date();
			}
			
			// Close all the services
			$closedServices = $this->closeServices($newEntity, $oldEntity);
			if($closedServices) {
				$oldEntity['services'] = $closedServices;
			}
			
			$this->closeEntity($this->oldEntity);
		} catch (\MongoException $e) {
			$errorCode =  1;
			Billrun_Factory::log($e->getCode() . ": " . $e->getMessage(), Billrun_Log::WARN);
			$this->reportError($errorCode, Zend_Log::NOTICE);
		}

		$outputResult = array(
			'status' => 1,
			'desc' => "Success updating subscriber",
		);

		if (isset($oldEntity)) {
			$outputResult['details']['before'] = $oldEntity->getRawData();
		}
		if (isset($newEntity)) {
			$outputResult['details']['after'] = $newEntity->getRawData();
		}
		return $outputResult;
	}
	
	/**
	 * Get the old entity
	 * @return false if not found or Mongodloid_Entity
	 */
	protected function getOldEntity() {
		$old = Billrun_Factory::db()->subscribersCollection()->query($this->query)->cursor()->current();
		if ($old->isEmpty()) {
			return false;
		}
		return $old;
	}
	
	protected function updateEntity($oldEntity){
		$new = $oldEntity->getRawData();
		unset($new['_id']);
		$new['from'] = $this->time;
		foreach ($this->update as $field => $value) {
			$new[$field] = $value;
		}
		
		$oldPlan = null;
		$oldServices = array();
		if(isset($oldEntity['plan'])) {
			$oldPlan = $oldEntity['plan'];
		}
		if(isset($oldEntity['services'])) {
			$oldServicesRaw = $oldEntity['services'];
			$oldServices = $this->filterOldServices($oldServicesRaw);
		}
		
		$newEntity = new Billrun_Subscriber_Entity($new, $oldPlan, $oldServices);
		$this->collection->save($newEntity, 1);
		return $newEntity;
	}
	
	protected function filterOldServices($oldServices) {
		$filtered = array();
		// Remove old service entities.
		foreach ($oldServices as $service) {
			// Check if it is deactivated.
			if(isset($service['deactivation'])) {
				continue;
			}
			
			$filtered[] = $service;
		}
		return $filtered;
	}
	
	protected function closeEntity($entity) {
		$entity['to'] = $this->time;
		$this->collection->save($entity, 1);
	}
	
	protected function extractServices($record) {
		if(!isset($record['services'])) {
			return array();
		}
		
		return $record['services'];
	}
	
	/**
	 * Get the array of closed services according to the new record
	 * @param type $new
	 * @param type $old
	 * @return type
	 */
	protected function closeServices($new, $old) {
		$oldServices = $this->extractServices($old);
		if(!$oldServices) {
			return array();
		}
		
		$newServices = $this->extractServices($new);
		
		// Get deactivated
		$deactivated = array();
		foreach ($newServices as $newService) {
			if(isset($newService['deactivation'])) {
				$deactivated[$newService['name']] = $newService['deactivation'];
			}
		}
		
		// Go through the old services
		foreach ($oldServices as &$oldService) {
			if(isset($oldService['deactivation'])) {
				continue;
			}
			$name = $oldService['name'];
			if(isset($deactivated[$name])) {
				$oldService['deactivation'] = $deactivated[$name];
			}
		}
		
		return $oldServices;
	}
	
	/**
	 * Parse the received request.
	 * @param type $input - Input received.
	 * @return true if valid.
	 */
	public function parse($input) {
		// Translate
		if (!parent::parse($input) || !$this->setQueryRecord($input)) {
			return false;
		}
		
		$this->oldEntity = $this->getOldEntity();
		if($this->oldEntity === false) {
			// [SUBSCRIBERS error 1037]
			$errorCode =  37;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}
		
		return $this->validate();
	}

	protected function validateOverlap() {
		$this->validatorData['_id'] = $this->oldEntity['_id'];
		if(!isset($this->validatorData['sid']) && isset($this->query['sid'])) {
			$this->validatorData['sid'] = $this->query['sid'];
		}
		if(!isset($this->validatorData['aid'])) {
			$this->validatorData['aid'] = $this->query['aid'];
		}
		$this->validatorData['type'] = $this->type;
		return $this->baseValidateOverlap(false);
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
			$errorCode =  2;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}
		
		$translated = $this->handleMongoId($jsonData);
		$invalidFields = $this->setQueryFields($translated);
		
		// If there were errors.
		if (!empty($invalidFields)) {
			throw new Billrun_Exceptions_InvalidFields($invalidFields);
		}

		$update = $input->get('update');
		if (empty($update) || (!($jsonData = json_decode($update, true))) || !$this->setUpdateFields($jsonData)) {
			$errorCode =  2;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}
		
		// Enforce the update.
		$this->enforce($this->fields, $jsonData);

		return true;
	}

	/**
	 * Handle the mongo ID inside the received input
	 * @param array $input
	 * @return The input array with the mongo ID translated.
	 */
	protected function handleMongoId(array $input) {
		$result = $input;
		
		// Check that it exists.
		if(!isset($input['_id']) || !($input['_id'])) {
			$this->reportError(42, Zend_Log::NOTICE);
		}
		
		$id = isset($input['_id']['$id'])? $input['_id']['$id'] : $input['_id'];
		try {
			$mongoID = new Mongodloid_Id($id);
			
			// Set the mongo ID in the input array
			$result['_id'] = $mongoID;
		} catch (MongoException $ex) {
			$this->reportError(43, Zend_Log::NOTICE, array($id));			
		}
		
		return $result;
	}
	
	/**
	 * Set all the query fields in the record with values.
	 * @param array $queryData - Data received.
	 * @return array - Array of strings of invalid field name. Empty if all is valid.
	 */
	protected function setQueryFields($queryData) {
//		$this->query = Billrun_Utils_Mongo::getDateBoundQuery();
		
		// Get the mongo ID
		$id = $queryData['_id'];		
		$this->query['_id'] = $id;
		
//		$queryMandatoryFields = array('_id');
//		
		// Array of errors to report if any error occurs.
		$invalidFields = array();
//
//		// Get only the values to be set in the update record.
//		foreach ($queryMandatoryFields as $fieldName) {
//			if (!isset($queryData[$fieldName]) || empty($queryData[$fieldName])) {
//				$invalidFields[] = new Billrun_DataTypes_InvalidField($fieldName);
//			} else if (isset($queryData[$fieldName])) {
//				$this->query[$fieldName] = $queryData[$fieldName];
//			}
//		}

		return $invalidFields;
	}
	
	/**
	 * Set all the update fields in the record with values.
	 * @param array $updateData - Data received.
	 * @return bool
	 */
	protected function setUpdateFields($updateData) {
		// Get only the values to be set in the update record.
		foreach ($this->fields as $field) {
			$fieldName = $field['field_name'];
			if (isset($field['editable']) && !$field['editable']) {
				continue;
			}
			if (!isset($updateData[$fieldName])) {
				continue;
			}			
			$this->update[$fieldName] = $updateData[$fieldName];
		}
		
		return true;
	}

	protected function getSubscriberData() {
		$oldData = $this->oldEntity->getRawData();
		$subscriberData = array_merge($oldData, $this->update);
		
		return $subscriberData;
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
		$query['_id'] = array(
			'$ne' => $this->query['_id'],
		);
		return $query;
	}
	
	protected function getBypassList() {
		return array('mandatory');
	}
}
