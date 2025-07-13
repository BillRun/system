<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2025 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * A flexible, parameter-driven Trait for managing multiple operation locks using a fixed expiry time.
 */
trait Billrun_Traits_Api_FlexibleOperationsLock
{
    /**
     * Locks an operation to prevent concurrent execution.
     *
     * @param array $lockIdentifier Key-value pairs that uniquely identify the operation (e.g., ['action' => 'billing', 'cycle' => '2025-07']).
     * @param int   $lockLifetime   Optional. The number of hours for which the lock should be valid. Defaults to 24.
     * @param array $conflictQuery  Optional. Additional MongoDB query conditions to identify a conflicting operation.
     * @return bool                 Returns true if the lock was acquired successfully, false otherwise.
     */
    public function lock(array $lockIdentifier, int $lockLifetime = 24, array $conflictQuery = [])
    {
        $operationsColl = Billrun_Factory::db()->operationsCollection();
        $now = new Mongodloid_Date();
        $expiryTimestamp = strtotime('+' . $lockLifetime . ' hours', $now->sec);

        $newInsert = [
            'lock_start_time' => $now,
            'lock_expiry_time' => new Mongodloid_Date($expiryTimestamp),
        ];
        
        $updateQuery = array_merge($lockIdentifier, $newInsert);

        $lockCondition = [
            '$and' => [
                ['lock_end_time' => ['$exists' => false]],
                ['lock_expiry_time' => ['$gt' => $now]],
            ],
        ];

        if (!empty($conflictQuery)) {
            $lockCondition['$and'][] = $conflictQuery;
        }

        $query = array_merge($lockIdentifier, $lockCondition);

        Billrun_Factory::log("Attempting to lock operation: " . json_encode($lockIdentifier), Zend_Log::DEBUG);
        // if it finds a similar active lock - do nothing. else create a new one. (THIS IS ATOMIC)
        $updateOperation = $operationsColl->findAndModify(
            $query,
            ['$setOnInsert' => $updateQuery],
            [], //empty update part
            ['upsert' => true]
        );

        if ($updateOperation->isEmpty()) {
            Billrun_Factory::log("Successfully acquired lock: " . json_encode($lockIdentifier), Zend_Log::DEBUG);
            return true;
        }

        Billrun_Factory::log("Operation is already locked by a valid, non-expired lock: " . json_encode($lockIdentifier), Zend_Log::DEBUG);
        return false;
    }

    /**
     * Releases an active operation lock.
     *
     * @param array $lockIdentifier Key-value pairs that uniquely identify the operation to release.
     * @return bool                 Returns true if the lock was released, false if no active lock was found.
     */
    public function release(array $lockIdentifier)
    {
        $operationsColl = Billrun_Factory::db()->operationsCollection();
        $now = new Mongodloid_Date();
        $query = array_merge($lockIdentifier, [
            'lock_end_time' => ['$exists' => false],
            'lock_expiry_time' => ['$gt' => $now ], 
        ]);

        Billrun_Factory::log("Releasing operation: " . json_encode($lockIdentifier), Zend_Log::DEBUG);
        
        $releaseOperation = $operationsColl->findAndModify($query, ['$set' => ['lock_end_time' => new Mongodloid_Date()]]);

        if (!$releaseOperation->isEmpty()) {
            Billrun_Factory::log("Operation lock released: " . json_encode($lockIdentifier), Zend_Log::DEBUG);
            return true;
        }
        
        Billrun_Factory::log("Could not find active lock to release: " . json_encode($lockIdentifier), Zend_Log::DEBUG);
        return false;
    }
}
