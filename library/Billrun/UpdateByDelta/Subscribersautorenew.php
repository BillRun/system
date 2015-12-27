<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Wrapper for an API updater class to be used by the update by delta
 * updater.
 * 
 * @package  Billing
 * @since    4.0
 * @author Tom Feigin
 */
class Billrun_UpdateByDelta_Subscribersautorenew extends Billrun_UpdateByDelta_Updater {
	
	/**
	 * Array of the mendatory fields.
	 * @var array
	 */
	protected $mendatoryFields = array();
		
	/**
	 * Get the collection for the API
	 */
	public function getCollection() {
		return Billrun_Factory::db()->subscribers_auto_renew_servicesCollection();
	}

	/**
	 * Get the array of translate values of the query field names and the corresponding
	 * names found in the entity.
	 * @return array of translate fields.
	 */
	protected function getQueryTranslateFields() {
		return Billrun_Factory::config()->getConfigValue('autorenew.query_translate_fields');
	}
	
	/**
	 * Get the array of translate values of the update query field names and the corresponding
	 * names found in the entity.
	 * @return array of translate fields.
	 */
	protected function getUpdateQueryTranslateFields() {
		return Billrun_Factory::config()->getConfigValue('autorenew.update_translate_fields');
	}
	
	/**
	 * Get the API updater input by an input entity to update/create.
	 * @param array $entity - Entity to get the updater input by.
	 * @return true if successful.
	 */
	protected function getUpdaterInput($entity) {
		$query = $this->getQueryByEntity($entity);
		if($query === false) {
			// TODO: ERROR?
			return false;
		}
		$upsert = $this->getUpdateByEntity($entity);
		if($upsert === false) {
			// TODO: ERROR?
			return false;
		}
		
		return json_encode(array("query" => $query, "upsert" => $upsert));
	}
	
	/**
	 * Update the subscribers auto renew collection by an entity.
	 * @param array $entity - Entity to update the mongo with.
	 * @return true if successful.
	 */
	protected function updateByEntity($entity) {
		$updaterInput = $this->getUpdaterInput($entity);
		if($updaterInput === false) {
			return false;
		}
		
		$updater = new Billrun_ActionManagers_SubscribersAutoRenew_Update();
		if(!$updater->parse($updaterInput)) {
			// TODO: Report error.
			return false;
		}
		
		return $updater->execute();
	}
	
	/**
	 * Create a record in the subscribers auto renew collection.
	 * @param array $entity - Entity to create in the mongo.
	 * @return true if successful.
	 */
	protected function createByEntity($entity) {
		return $this->updateByEntity($entity);
	}

	/**
	 * Get the array of keys that are used to compare the delta between records.
	 * @return array of keys.
	 */
	protected function getKeys() {
		return Billrun_Factory::config()->getConfigValue('autorenew.delta_keys');
	}

	/**
	 * Check if a field is mendatory 
	 * @param string $field - Field to check
	 * @return true if the field is mendatory.
	 */
	protected function isMendatoryField($field) {
		if(empty($this->mendatoryFields)) {
			$this->mendatoryFields = Billrun_Factory::config()->getConfigValue('autorenew.mendatory', array());
		}
		
		return in_array($field, $this->mendatoryFields);
	}

}
