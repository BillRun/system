<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2017 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This Trait is used for API modules that manage cycle management.
 *
 */
trait Billrun_Traits_Api_OperationsLock {

	protected static $orphanTime = '1 day ago';

	/**
	 * Returns the data of the operation that request to lock it.
	 *
	 */
	protected abstract static function getInsertData();
	
	/**
	 * Returns the conflicting conditions of the selected operation.
	 *
	 */
	protected abstract function getConflictingQuery();
	
	/**
	 * Returns the details of the operation to release.
	 *
	 */
	protected abstract function getReleaseQuery();
		
	/**
	 * Locks operation from get executed again before the first one ended.
	 *
	 */
	public function lock() {
		$operationsColl = Billrun_Factory::db()->operationsCollection();
		$data = static::getInsertData();
		$newInsert = array(
			'start_time' => new Mongodloid_Date(),
		);
		$conflict = static::getConflictingQuery();
		$updateQuery = array_merge($data, $newInsert);
		if (!empty($conflict)) {
			$lockCondition = array(
				'$and' => array(
					array('end_time' => array('$exists' => false)),
					array('start_time' => array('$gt' => new Mongodloid_Date(strtotime(static::$orphanTime)))),
					$conflict
				)
			);
		} else { 
			$lockCondition = array(
				'$and' => array(
					array('end_time' => array('$exists' => false)),
					array('start_time' => array('$gt' => new Mongodloid_Date(strtotime(static::$orphanTime)))),
				)
			);
		}
		unset($data['filtration']);
		$query = array_merge($data, $lockCondition);
		Billrun_Factory::log("Locking operation " . $data['action'], Zend_Log::DEBUG);
		$updateOperation = $operationsColl->findAndModify($query, array('$setOnInsert' => $updateQuery), array(),  array('upsert' => true));
		if ($updateOperation->isEmpty()) {
			Billrun_Factory::log("Operation " . $data['action'] . ' was locked', Zend_Log::DEBUG);
			return true;
		}
		return false;
	}

		
	/**
	 * Releasing operation so it can be executed once more.
	 *
	 */
	public function release() {
		$operationsColl = Billrun_Factory::db()->operationsCollection();
		$query = static::getReleaseQuery();
		Billrun_Factory::log("Releasing operation " . $query['action'], Zend_Log::DEBUG);
		$releaseOperation = $operationsColl->findAndModify($query, array('$set' => array('end_time' => new Mongodloid_Date())));
		Billrun_Factory::log("Operation " . $query['action'] . ' was released', Zend_Log::DEBUG);
		if (!$releaseOperation->isEmpty()){
			return true;
		}
		return false;
	}
	
}