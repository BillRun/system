<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Util class for the Audit Trail
 *
 * @package  Util
 * @since    5.5
 */
class Billrun_AuditTrail_Util {

	/**
	 * Log an audit trail event (by adding it to 'log' collection with source='audit')
	 * 
	 * @param string $type
	 * @param string $key
	 * @param string $collection
	 * @param array $old
	 * @param array $new
	 * @param array $additionalParams
	 * @return boolean true on success, false otherwise
	 */
	public static function trackChanges($type = '', $key = '', $collection = '', $old = null, $new = null, array $additionalParams = array()) {
		Billrun_Factory::log("Track changes in audit trail", Zend_Log::DEBUG);
		try {
			$trackUser = static::getUser();
			$logEntry = static::createLogEntry($trackUser, $type, $key, $collection, $old, $new, $additionalParams);
			Billrun_Factory::db()->auditCollection()->save($logEntry);
			Billrun_Factory::dispatcher()->trigger('trackChanges', array($old, $new, $collection, $type, $trackUser));
			return true;
		} catch (Exception $ex) {
			Billrun_Factory::log('Failed on insert to audit trail. ' . $ex->getCode() . ': ' . $ex->getMessage(), Zend_Log::ERR);
		}
		return false;
	}

	/**
	 * Log audit trail events (by adding them to 'log' collection with source='audit')
	 * 
	 * @param string $type
	 * @param string $keyField
	 * @param string $collection
	 * @param array $oldRevisions - mapped by revision _id
	 * @param array $newRevisions - mapped by revision _id
	 * @param array $additionalParams
	 * @return number of success audits, false in a caseof failure
	 */
	public static function trackMultipleChanges($type = '', $keyField = '', $collection = '', $oldRevisions = [], $newRevisions, array $additionalParams = array()) {
		Billrun_Factory::log("Track changes in audit trail", Zend_Log::DEBUG);
		$logEntrys = [];
		$trackUser = static::getUser();
		foreach ($newRevisions as $_id => $newRevision) {
			$oldRevision = $oldRevisions[$_id];
			$key = $oldRevision[$keyField];
			if($oldRevision === null){
				throw new Exception("No old Revision was found by _id: $_id.");
			}
			$logEntrys[] = static::createLogEntry($trackUser, $type, $key, $collection, $oldRevision, $newRevision, $additionalParams);
		}
		try {
			$res = Billrun_Factory::db()->auditCollection()->batchInsert($logEntrys);
			if ($res['ok']) {
				Billrun_Factory::log("Tracked " . $res['nInserted'] . " revisions.", Zend_Log::DEBUG);
				Billrun_Factory::dispatcher()->trigger('trackMultipleChanges', array($oldRevisions, $newRevisions, $collection, $type, $trackUser));
				return $res['nInserted'];
			} else {
				Billrun_Factory::log("Failed tracking revisions.", Zend_Log::DEBUG);
				return false;
			}
		} catch (Exception $ex) {
			Billrun_Factory::log('Failed on insert to audit trail. ' . $ex->getCode() . ': ' . $ex->getMessage(), Zend_Log::ERR);
		}
		return false;
	}

	protected static function getUser() {
		$user = Billrun_Factory::user();
		if ($user) {
			$trackUser = array(
				'_id' => $user->getMongoId()->getMongoID(),
				'name' => $user->getUsername(),
			);
		} else { // in case 3rd party API update with token => there is no user
			$trackUser = array(
				'_id' => null,
				'name' => '_3RD_PARTY_TOKEN_',
			);
		}
		return $trackUser;
	}

	protected static function createLogEntry($trackUser = null, $type = '', $key = '', $collection = '', $old, $new, array $additionalParams = array()) {
		$basicLogEntry = array(
			'source' => 'audit',
			'collection' => $collection,
			'type' => $type,
			'urt' => new Mongodloid_Date(),
			'user' => $trackUser,
			'old' => $old,
			'new' => $new,
			'key' => $key,
		);
		
		$logEntry = array_merge($basicLogEntry, $additionalParams);
		$logEntry['stamp'] = Billrun_Util::generateArrayStamp($logEntry);
		return new Mongodloid_Entity($logEntry);
	}
}
