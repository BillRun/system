
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
			$this->time = new MongoDate();
			if (!$oldEntity = $this->getOldEntity()) {
				return false;
			}
			$newEntity = $this->updateEntity($this->oldEntity);
				
			// Check if changed plans.
			if($newEntity['plan'] !== $this->oldEntity['plan']) {
				$this->oldEntity['plan_deactivation'] = new MongoDate();
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
		$newEntity = new Billrun_Subscriber_Entity($new, $oldEntity['plan']);
		$this->collection->save($newEntity, 1);
		return $newEntity;
	}
	
	protected function closeEntity($entity) {
		$entity['to'] = $this->time;
		$this->collection->save($entity, 1);
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
		
		$id = $input['_id'];
		try {
			$mongoID = new MongoId($id);
			
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
		$this->query = Billrun_Utils_Mongo::getDateBoundQuery();
		
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
			
			// TODO: Create some sort of polymorphic behaviour to correctly handle
			// the updating fields.
			if($fieldName === 'services') {
				$toSet = $this->getSubscriberServices($updateData['services']);
			} else {
				$toSet = $updateData[$fieldName];
			}
			
			if(empty($toSet)) {
				continue;
			}
			
			$this->update[$fieldName] = $toSet;
		}
		
		return true;
	}

	protected function getSubscriberData() {
		$oldData = $this->oldEntity->getRawData();
		$subscriberData = array_merge($oldData, $this->update);
		
		return $subscriberData;
	}

	protected function _getCollection() {
		return Billrun_Factory::db()->subscribersCollection();
	}
	
	protected function _getBaseQuery() {
		return array('type' => $this->type);
	}
}
