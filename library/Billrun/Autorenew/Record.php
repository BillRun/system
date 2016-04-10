<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 4; see LICENSE.txt
 */

/**
 * Abstract helper class for an auto renew record
 *
 * @author tom
 */
abstract class Billrun_Autorenew_Record {
	
	/**
	 * Holds the record data.
	 * @var Array
	 */
	protected $data;
	
	/**
	 * Create a new instance of the auto renew record object.
	 * @param array $record - The record from the data base.
	 */
	public function __construct($record) {
		// TODO: I believe that this condition is correct, it might change if we
		// change the database framework.
		if(!is_a($record, "Mongodloid_Entity")) {
			throw new Exception;
		}
		$this->data = clone $record;
	}
	
	/**
	 * Get the next renew date for this recurring plan.
	 * @return MongoDate Next update date.
	 */
	protected abstract function getNextRenewDate();
	
	/**
	 * Get the date to set in the balance 'to' field.
	 * @param MongoDate $nextRenewDate - The next renew date for the autorenew record
	 * @return MongoDate date to set in the 'to' field.
	 */
	protected function getUpdaterInputToTime($nextRenewDate) {
		$dataTo = $this->data['to'];
		if(is_a($dataTo, "MongoDate")) {
			$toTime = $dataTo->sec;
		} else {
			$toTime = strtotime($dataTo);
		}
		
		
		$toDate = $nextRenewDate;
		
		// Check if the 'to' is before the next renew date.
		if($nextRenewDate->sec > $toTime) {
			$toDate = new MongoDate($toTime);
		}
		
		return $toDate;
	}
	
	/**
	 * Get the balance updater input.
	 * @param MongoDate $nextRenewDate - The next renew date for the autorenew record
	 * @return array - Input array for the balance updater.
	 */
	protected function getUpdaterInput($nextRenewDate) {
		$updaterInput['method'] = 'update';
		$updaterInput['sid'] = $this->data['sid'];

		// Set the recurring flag for the balance update.
		$updaterInput['recurring'] = 1;
		
		// Build the query
		$updaterInputQuery['charging_plan_external_id'] = $this->data['charging_plan_external_id'];
		$updaterInputUpdate['from'] = $this->data['from'];
		$updaterInputUpdate['to'] = $updaterInputUpdate['expiration_date'] = $this->getUpdaterInputToTime($nextRenewDate);
		$updaterInputUpdate['operation'] = $this->data['operation'];
		
		$updaterInput['query'] = json_encode($updaterInputQuery,JSON_FORCE_OBJECT);
		$updaterInput['upsert'] = json_encode($updaterInputUpdate,JSON_FORCE_OBJECT);
		
		// Send the additional field if exists.
		if(isset($this->data['additional'])) {
			$updaterInput['additional'] = json_encode($this->data['additional']);
		}
		
		return $updaterInput;
	}
	
	/**
	 * Update a balance according to a auto renew record.
	 * @param MongoDate $nextRenewDate - The next renew date for the autorenew record.
	 * @return boolean
	 */
	protected function updateBalance($nextRenewDate) {
		$updaterInput = $this->getUpdaterInput($nextRenewDate);
		$updater = new Billrun_ActionManagers_Balances_Update(); 
		
		// Anonymous object
		$jsonObject = new Billrun_AnObj($updaterInput);
		if(!$updater->parse($jsonObject)) {
			// TODO: What do I do here?
			return false;
		}
		if(!$updater->execute()) {
			// TODO: What do I do here?
			return false;
		}
		
		return true;
	}
	
	/**
	 * Update the auto renew record.
	 * @param MongoDate $nextRenewDate - The next renew date for the autorenew record
	 * @return Result of the update operation.
	 */
	protected function updateAutorenew($nextRenewDate) {
		if(is_a($nextRenewDate, 'MongoDate')) {
			$nextRenewDate->sec += 1;
		} else {
			$nextRenewDate = new MongoDate($nextRenewDate + 1);
		}
		
		$this->data['last_renew_date'] = new MongoDate();
		$this->data['next_renew_date'] = $nextRenewDate;
		$this->data['remain'] = $this->data['remain'] - 1;
		
		$this->data['done'] = $this->data['done'] + 1;

		$collection = Billrun_Factory::db()->subscribers_auto_renew_servicesCollection();
		return $collection->updateEntity($this->data);		
	}
	
	/**
	 * Update the auto renew record after usage.
	 * @return the update function result.
	 */
	public function update() {
		$nextRenewDate = $this->getNextRenewDate();
		
		if(!$this->updateBalance($nextRenewDate)) {
			// TODO: This means that if we failed to update the balance we do not
			// update the auto renew record!!!
			return false;
		}
		
		// The next auto renew is one second after the balance expiration input
		return $this->updateAutorenew($nextRenewDate);
	}
}
