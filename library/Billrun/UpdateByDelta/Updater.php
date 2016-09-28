<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Updating by delta class
 * 
 * DB transfer layer
 * 
 * @package  Billing
 * @since    4.0
 * 
 * @todo Inherit from the error report class/interface to enable dynamic
 * error reporting
 */
abstract class Billrun_UpdateByDelta_Updater {

	use Billrun_ActionManagers_ErrorReporter;

	public function __construct() {
		$this->baseCode = 1300;
	}
	
	/**
	 * Update the collection by an entity.
	 * @param array $entity - Entity to update the mongo with.
	 * @return true if successful.
	 */
	protected abstract function updateByEntity($entity);

	/**
	 * Create a record in the collection.
	 * @param array $entity - Entity to create in the mongo.
	 * @return true if successful.
	 */
	protected abstract function createByEntity($entity);

	/**
	 * Get the array of translate values of the update query field names and the corresponding
	 * names found in the entity.
	 * @return array of translate fields.
	 */
	protected abstract function getUpdateQueryTranslateFields();

	/**
	 * Get the array of translate values of the query field names and the corresponding
	 * names found in the entity.
	 * @return array of translate fields.
	 */
	protected abstract function getQueryTranslateFields();

	/**
	 * Get the collection for the API
	 * @return Mongodloid_Collection the collection for the current updater.
	 */
	protected abstract function getCollection();

	/**
	 * Add all the values to the data base.
	 * @param array $values - Array of values to add.
	 * @return true if successful.
	 */
	protected function addValues($values) {
		$success = true;
		foreach ($values as $toAdd) {
			$success = $this->createByEntity($toAdd) && $success;
		}

		return $success;
	}

	/**
	 * Return an array of field names used as keys in order 
	 * to compare the records properly
	 */
	protected abstract function getKeys();

	/**
	 * Check if a field is mendatory for creating/updating an entity.
	 * @param string $field - Field to check.
	 * @return true if mendatory.
	 */
	protected abstract function isMendatoryField($field);

	/**
	 * Get the mongo query by an entity.
	 * @param array $entity - Entity to build the query by.
	 * @param array $translateFields - Array of translate values to 
	 * translate the entity by.
	 * @return array query composed by the input entity.
	 */
	protected function translateEntityToQuery($entity, $translateFields) {
		$query = array();
		foreach ($translateFields as $field => $translate) {
			// TODO: This is to "fix" the weird empty fields in the config file.
			if (empty($field)) {
				Billrun_Factory::log("Received empty values from the config file", Zend_Log::NOTICE);
				continue;
			}

			if ($this->isMendatoryField($field) && !isset($entity[$translate])) {
				$errorCode = 16;
				$this->reportError($errorCode, Zend_Log::NOTICE, array($translate));
				return false;
			}

			if (!isset($entity[$translate])) {
				Billrun_Factory::log("Field " . $translate . " is missing in translated entity", Zend_Log::NOTICE);
				continue;
			}

			$query[$field] = $entity[$translate];
		}

		return $query;
	}

	/**
	 * Get the mongo query by an entity.
	 * @param array $entity - Entity to build the query by.
	 * @return array query composed by the input entity.
	 */
	protected function getQueryByEntity($entity) {
		$queryTranslateFields = $this->getQueryTranslateFields();
		return $this->translateEntityToQuery($entity, $queryTranslateFields);
	}

	/**
	 * Translate a single field
	 * @param array $field - Field to translate.
	 * @return string translated.
	 */
	protected function translateField($field) {
		$queryTranslateFields = $this->getQueryTranslateFields();
		return $queryTranslateFields[$field];
	}

	/**
	 * Get the mongo update query by an entity.
	 * @param array $entity - Entity to build the update query by.
	 * @return array update query composed by the input entity.
	 */
	protected function getUpdateByEntity($entity) {
		$queryTranslateFields = $this->getUpdateQueryTranslateFields();
		return $this->translateEntityToQuery($entity, $queryTranslateFields);
	}

	/**
	 * Melt the records to be added with the default record.
	 * @param array $toBeAdded - Array of records to be added.
	 * @param array $defaultRecord - The default record to use.
	 */
	protected function meltWith($toBeAdded, $defaultRecord) {
		$melted = array();
		foreach ($toBeAdded as $record) {
			$temp = $defaultRecord;
			foreach ($record as $key => $value) {
				$temp[$key] = $value;
			}
			$melted[] = $temp;
		}

		return $melted;
	}

	/**
	 * Execute the main logic
	 * @param array $query - Query to get data from the collection.
	 * @param array $expectedReults - Array of json results to compare to 
	 * the ones in the data base.
	 * @param array $defaultRecord - A default record to wrap the existing 
	 * values with if missing fields.
	 * @return true if successful.
	 * @todo validate the input params?
	 */
	public function execute($query, $expectedReults, $defaultRecord = array()) {
		$existingRecords = $this->getExistingRecords($query);

		$toBeAdded = $this->handleDeltaArrays($expectedReults, $existingRecords);

		$valuesToAdd = $this->meltWith($toBeAdded, $defaultRecord);

		// Add all the values.
		return $this->addValues($valuesToAdd);
	}

	/**
	 * Fill the expected record with the values of the existing record.
	 * @param array $existing
	 * @param array $expected
	 * @return Merged record
	 */
	protected function getMatched($existing, $expected) {
		$matched = $existing;
		foreach ($expected as $key => $value) {
			$matched[$key] = $value;
		}
		return $matched;
	}

	/**
	 * Handle the delta updating of a single record.
	 * @param array $expectedReults - Array of the expected results
	 * @param array $existing - Existing record in the mongo.
	 * @return a matched entity or false otherwise.
	 */
	protected function handleSingleRecord($expectedReults, $existing) {
		$matched = false;
		foreach ($expectedReults as $expected) {
			// If the result is not 1 it means that we found the 
			// record to update the existing by.
			// TODO: Throws expection/returns error?
			if ($this->handleDelta($existing, $expected) !== 1) {
				$matched = $this->getMatched($existing, $expected);
				break;
			}
		}

		// If the record was not updated but we went through all the expected 
		// results, it means that the record should be deleted.
		if ($matched === false) {
			// Set the "to" date to now.

			if (!$this->deleteEntity(new Mongodloid_Entity($existing))) {
				Billrun_Factory::log("Failed to delete record: " . print_r($existing, 1), Zend_Log::ERR);
			}
		}

		return $matched;
	}

	/**
	 * Handle the delta between the arrays.
	 * @param array expectedResults - Array of the expected results.
	 * @return array of records that are expected but not found.
	 */
	protected function handleDeltaArrays($expectedResults, $existingRecords) {
		$expectedMatched = array();

		// Go through the existing records.
		foreach ($existingRecords as $existing) {
			$matched = $this->handleSingleRecord($expectedResults, $existing);
			if ($matched !== false) {
				$expectedMatched[] = $matched;
			}
		}

		return @array_diff_assoc($expectedResults, $expectedMatched);
	}

	/**
	 * Delete an entity that exist in the mongo but does not exist in the 
	 * expected values.
	 * @param Mongodloid_Entity $entity - Entity to delete
	 * @return true if successful.
	 */
	protected function deleteEntity($entity) {
		$deleteQuery = array("to" => new MongoDate());
		return $this->getCollection()->updateEntity($entity, $deleteQuery);
	}

	/**
	 * Update a record in the mongo by a delta.
	 * @param array $original - The original values.
	 * @param array $delta - The delta values.
	 */
	protected function updateRecordByDiff($original, $delta) {
		if (is_array($original)) {
			$entity = new Mongodloid_Entity($original, $this->getCollection());
		} else {
			$entity = $original;
		}

		// TODO: Throws expection/returns error?
		return $this->getCollection()->updateEntity($entity, $delta);
	}

	/**
	 * Handle the delta between existing and expected records.
	 * @param json $existing
	 * @param json $expected
	 * @param Mongodloid_Collection $collection
	 * @return int 1 if not matched, otherwise the result of the record's update.
	 * @todo This is to be implemented by each API wrapper!!!
	 */
	protected function handleDelta($existing, $expected) {
		$keys = $this->getKeys();
		// Check if the record should exist.
		foreach ($keys as $fieldKey) {
			if ($existing[$fieldKey] != $expected[$fieldKey]) {
				return 1;
			}
		}

		// Update the record.
		return $this->updateRecordByDiff($existing, array_diff_assoc($expected, $existing));
	}

	/**
	 * Get the existing records in the mongo.
	 * @param aray $query - Query to use for getting the existing elements.
	 * @return array of Json objects.
	 */
	protected function getExistingRecords($query) {
		$existingRecords = array();

		// Run the query on the collection.
		$cursor = $this->getCollection()->query($query)->cursor();

		foreach ($cursor as $record) {
			$existingRecords[] = $record->getRawData();
		}

		return $existingRecords;
	}

}
