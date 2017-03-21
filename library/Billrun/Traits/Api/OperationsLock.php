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
			'start_time' => new MongoDate(),
		);
		$conflict = static::getConflictingQuery();
		$updateQuery = array_merge($data, $newInsert);
		if (!empty($conflict)) {
			$lockCondition = array(
				'$and' => array(
					array('end_time' => array('$exists' => false)),
					array('start_time' => array('$gt' => new MongoDate(strtotime(static::$orphanTime)))),
					$conflict
				)
			);
		} else { 
			$lockCondition = array(
				'$and' => array(
					array('end_time' => array('$exists' => false)),
					array('start_time' => array('$gt' => new MongoDate(strtotime(static::$orphanTime)))),
				)
			);
		}
		unset($data['aids']);
		$query = array_merge($data, $lockCondition);
		$updateOperation = $operationsColl->findAndModify($query, array('$setOnInsert' => $updateQuery), array(),  array('upsert' => true));
		if ($updateOperation->isEmpty()) {
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
		$releaseOperation = $operationsColl->findAndModify($query, array('$set' => array('end_time' => new MongoDate())));
		if (!$releaseOperation->isEmpty()){
			return true;
		}
		return false;
	}
	
}