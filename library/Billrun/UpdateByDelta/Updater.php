<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Updating by delta class
 * 
 * DB transfer layer
 * 
 * @package  Billing
 * @since    4.0
 * @author Tom Feigin
 */
class Billrun_UpdateByDelta_Updater {
	
	/**
	 * Execute the main logic
	 * @param array $query - Query to get data from the collection.
	 * @param array $expectedReults - Array of json results to compare to 
	 * the ones in the data base.
	 * @param Mongodloid_Collection $collection
	 * @param array $keys array of the field names used as keys in order 
	 * to compare the records properly.
	 * @todo validate the input params?
	 */
	public function execute($query, $expectedReults, $collection, $keys) {
		$existingRecords = $this->getExistingRecords($query, $collection);
		
		$toBeAdded = 
			$this->handleDeltaArrays($expectedReults, $existingRecords, $collection, $keys);
		
		// Add all the values.
		foreach ($toBeAdded as $value) {
			// TODO: Error handling?
			$collection->insert($value);
		}
	}
	
	/**
	 * Handle the delta between the arrays.
	 * @return array of records that are expected but not found.
	 */
	protected function handleDeltaArrays($expectedReults, $existingRecords, $collection, $keys) {
		$expectedMatched = array();
		
		// Go through the existing records.
		foreach ($existingRecords as $existing) {
			$wasUpdated = false;
			foreach ($expectedReults as $expected) {
				// If the result is not 1 it means that we found the 
				// record to update the existing by.
				// TODO: Throws expection/returns error?
				if($this->handleDelta($existing, $expected, $collection, $keys) !== 1) {
					$expectedMatched[] = $expected;
					$wasUpdated = true;
					break;
				}
			}
			
			// If the record was not updated but we went through all the expected 
			// results, it means that the record should be deleted.
			if(!$wasUpdated) {
				// Set the "to" date to now.
				// TODO: Throws expection/returns error?
				$collection->updateEntity($existing, array("to" => new MongoDate()));
			}
		}
		
		return array_diff($expectedReults, $expectedMatched);
	}
	
	/**
	 * Handle the delta between existing and expected records.
	 * @param json $existing
	 * @param json $expected
	 * @param Mongodloid_Collection $collection
	 * @param array $keys
	 * @return int 1 if not matched, otherwise the result of the record's update.
	 */
	protected function handleDelta($existing, $expected, $collection, $keys) {
		// Check if the record should exist.
		foreach ($keys as $fieldKey) {
			if($existing[$fieldKey] != $expected[$fieldKey]) {
				return 1;
			}
		}

		// Update the record.
		// TODO: Throws expection/returns error?
		return $collection->updateEntity($existing, array_diff($expected, $existing));
	}
	
	/**
	 * Get the existing records in the mongo.
	 * @param aray $query - Query to use for getting the existing elements.
	 * @param MongoCollection - The collection to execute the query on.
	 * @return array of Json objects.
	 */
	protected function getExistingRecords($query, $collection) {
		$existingRecords = array();

		// Run the query on the collection.
		$cursor = $collection->query($query)->cursor();
		
		foreach ($cursor as $record) {
			$existingRecords[] = json_encode($record->getRawData());
		}
		
		return $existingRecords;
	}
}
