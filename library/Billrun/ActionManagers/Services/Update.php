
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
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		$details = array();
		try {
			$this->time = new Mongodloid_Date();
			$oldEntity = $this->getOldEntity();
			if (!$oldEntity) {
				$this->reportError(42, Zend_Log::NOTICE);
			}
			$details = $this->updateEntity($oldEntity);
			
			$this->closeEntity($oldEntity);
		} catch (\MongoException $e) {
			$this->reportError(1, Zend_Log::NOTICE);
		}

		$outputResult = array(
			'status' => 1,
			'desc' => "Success updating service",
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

	protected function getOldEntity() {
		$old = Billrun_Factory::db()->servicesCollection()->query($this->query)->cursor()->current();
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
		$newEntity = new Mongodloid_Entity($new);
		$this->collection->save($newEntity, 1);
		return $newEntity;
	}
	
	protected function closeEntity($entity) {
		$entity['to'] = $this->time;
		$this->collection->save($entity, 1);
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
		
		$this->setQueryFields($jsonData);
		
		if(empty($this->query)) {
			$this->reportError(22, Zend_Log::NOTICE);
		}
		
		$update = $input->get('update');
		if (empty($update) || (!($jsonData = json_decode($update, true))) || !$this->setUpdateFields($jsonData)) {
			$this->reportError(2, Zend_Log::NOTICE);
			return false;
		}
		

		return true;
	}

	/**
	 * Set all the query fields in the record with values.
	 * @param array $queryData - Data received.
	 */
	protected function setQueryFields($queryData) {
		$this->query = Billrun_Utils_Mongo::getDateBoundQuery();
		
		$this->setMongoID($queryData);
		
		return true;
	}
	
	/**
	 * TODO: Use the translators instead.
	 */
	protected function setMongoID($queryData) {
		// Get the mongo ID.
		if(!isset($queryData['_id'])) {
			$invalidField = new Billrun_DataTypes_InvalidField('_id');
			throw new Billrun_Exceptions_InvalidFields(array($invalidField));
		}
		
		try {
			$this->query['_id'] = new MongoId($queryData['_id']);
		} catch (MongoException $ex) {
			$invalidField = new Billrun_DataTypes_InvalidField('_id',2);
			throw new Billrun_Exceptions_InvalidFields(array($invalidField));
		}
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
