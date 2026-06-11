<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2021 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * MongoDB sharding model.
 *
 * Port of mongo/sharding.js. Enables sharding on a database and shards the
 * collections that BillRun's workload benefits from. Sharding commands are
 * authenticated via {@see Billrun_Factory::admindb()} and routed against the
 * cluster's `admin` database explicitly.
 *
 * No-ops when not connected to a sharded cluster (mongos), so callers can
 * invoke unconditionally. Errors from individual shard commands are logged
 * but do not abort the run - this keeps re-runs idempotent on the common
 * "already sharded" case across MongoDB versions.
 *
 * Migration files may call {@see shardOne()} / {@see reshardOne()} to add or
 * change shard keys on collections introduced after the initial set.
 *
 * @package  Models
 * @since    5.25.0
 */
class ShardingModel {

	/**
	 * Default shard keys per collection. Mirrors mongo/sharding.js.
	 *
	 * @var array<string,array>
	 */
	protected $shardDefinitions = [
		'lines'    => ['stamp' => 1],
		'archive'  => ['stamp' => 1],
		'rates'    => ['key' => 1],
		'billrun'  => ['aid' => 1, 'billrun_key' => 1],
		'balances' => ['aid' => 1, 'sid' => 1],
		'audit'    => ['stamp' => 1],
		'queue'    => ['stamp' => 1],
	];

	/**
	 * Enable sharding on the target database and shard all default collections.
	 * No-op when not connected to a sharded cluster.
	 *
	 * @param string $dbName
	 * @param mixed  $controller optional controller for addOutput
	 * @return bool true on success, false on connect failure or non-cluster
	 */
	public function execute($dbName, $controller = null) {
		$adminDb = Billrun_Factory::admindb();
		if (empty($adminDb)) {
			$this->log($controller, 'Cannot connect with admin credentials for sharding');
			return false;
		}
		if (!$adminDb->isCluster()) {
			return false;
		}

		$this->log($controller, 'Running sharding on db: ' . $dbName);
		$this->enableSharding($adminDb, $dbName, $controller);

		foreach ($this->shardDefinitions as $coll => $key) {
			$this->shardCollection($adminDb, $dbName, $coll, $key, $controller);
		}

		if ($adminDb->compareServerVersion('6', '>=')) {
			$this->shardCollection($adminDb, $dbName, 'bills', ['aid' => 'hashed'], $controller);
		}
		if ($adminDb->compareServerVersion('8', '>=')) {
			$this->shardCollection($adminDb, $dbName, 'jobs_messages', ['md5' => 1], $controller);
		}

		return true;
	}

	/**
	 * Shard a single collection. Use from migration files that introduce a new
	 * collection requiring sharding.
	 *
	 * @param string $dbName     target database
	 * @param string $collection collection name (without db prefix)
	 * @param array  $shardKey   e.g. ['stamp' => 1] or ['aid' => 'hashed']
	 * @param mixed  $controller optional controller for addOutput
	 * @return bool true if attempted (errors are logged, not thrown), false if non-cluster
	 */
	public function shardOne($dbName, $collection, array $shardKey, $controller = null) {
		$adminDb = Billrun_Factory::admindb();
		if (empty($adminDb) || !$adminDb->isCluster()) {
			return false;
		}
		$this->shardCollection($adminDb, $dbName, $collection, $shardKey, $controller);
		return true;
	}

	/**
	 * Re-shard an existing collection with a new shard key.
	 * Requires MongoDB >= 5.0.
	 *
	 * @param string $dbName
	 * @param string $collection
	 * @param array  $newShardKey
	 * @param mixed  $controller optional controller for addOutput
	 * @return bool
	 */
	public function reshardOne($dbName, $collection, array $newShardKey, $controller = null) {
		$adminDb = Billrun_Factory::admindb();
		if (empty($adminDb) || !$adminDb->isCluster()) {
			return false;
		}
		$ns = $dbName . '.' . $collection;
		try {
			$adminDb->reshardCollection($ns, $newShardKey);
			$this->log($controller, 'Resharded ' . $ns);
			return true;
		} catch (Exception $e) {
			$this->log($controller, 'reshardCollection on ' . $ns . ': ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * @param Billrun_Db $adminDb
	 * @param string     $dbName
	 * @param mixed      $controller
	 */
	protected function enableSharding($adminDb, $dbName, $controller = null) {
		try {
			$adminDb->enableSharding($dbName);
			$this->log($controller, 'Enabled sharding on ' . $dbName);
		} catch (Exception $e) {
			$this->log($controller, 'enableSharding on ' . $dbName . ': ' . $e->getMessage());
		}
	}

	/**
	 * @param Billrun_Db $adminDb
	 * @param string     $dbName
	 * @param string     $collection
	 * @param array      $shardKey
	 * @param mixed      $controller
	 */
	protected function shardCollection($adminDb, $dbName, $collection, array $shardKey, $controller = null) {
		$ns = $dbName . '.' . $collection;
		try {
			$adminDb->shardCollection($ns, $shardKey);
			$this->log($controller, 'Sharded ' . $ns);
		} catch (Exception $e) {
			$this->log($controller, 'shardCollection on ' . $ns . ': ' . $e->getMessage());
		}
	}

	protected function log($controller, $message) {
		if ($controller && method_exists($controller, 'addOutput')) {
			$controller->addOutput($message);
		}
	}

}
