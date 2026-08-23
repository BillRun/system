<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2021 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * DB initialization model class.
 *
 * Handles creation of MongoDB collections with indexes, loading of base config,
 * base taxes, and the first admin user. Used by DbinitAction for full DB init
 * and by CreatetenantAction to initialize a new tenant's database.
 *
 * @package  Models
 * @since    5.25.0
 */
class DbinitModel {

	/**
	 * Base path to mongo resources (config.export, taxes.export)
	 * @var string
	 */
	protected $mongoBasePath;

	/**
	 * @param string $mongoBasePath optional override for the base path
	 */
	public function __construct($mongoBasePath = null) {
		$this->mongoBasePath = $mongoBasePath ?: APPLICATION_PATH . '/mongo/base';
	}

	/**
	 * Full initialization: create config collection, all other collections with
	 * indexes, optionally load base taxes and first user.
	 *
	 * Base taxes and the first user are only seeded when their respective
	 * collections did not already exist before this run - otherwise repeated
	 * --dbinit invocations would keep inserting duplicates. Callers can also
	 * opt out of either step via $params (e.g. CreatetenantAction provides its
	 * own admin user from the API request, so it passes create_first_user=false).
	 *
	 * @param Billrun_Db $db
	 * @param mixed      $controller optional controller for addOutput; pass null when called from non-action context
	 * @param array      $params options bag:
	 *                   - 'create_first_user' (bool, default true): seed users collection from mongo/first_users.json
	 *                   - 'create_base_taxes' (bool, default true): seed taxes collection from mongo/base/taxes.export
	 *                   - 'create_sharding' (bool, default true): run ShardingModel after schema creation
	 * @return bool true on success, false if any step threw an exception
	 */
	public function execute(Billrun_Db $db, $controller = null, array $params = []) {
		$createFirstUser = !isset($params['create_first_user']) || $params['create_first_user'];
		$createBaseTaxes = !isset($params['create_base_taxes']) || $params['create_base_taxes'];
		$shardingCluster = !isset($params['create_sharding']) || $params['create_sharding'];

		try {
			$existingCollections = $db->getCollectionNames();

			$this->initConfigCollection($db, $controller);
			$this->createCollections($db, $controller);

			if ($createBaseTaxes && !in_array('taxes', $existingCollections, true)) {
				$this->loadBaseTaxes($db, $controller);
			}
			if ($createFirstUser && !in_array('users', $existingCollections, true)) {
				$this->loadFirstUser($db, $controller);
			}

			if ($shardingCluster || empty($existingCollections)) {
				// Enable + apply sharding on the active DB when running on a mongos
				// cluster. Self-gated via ShardingModel::execute() -> isCluster(), so
				// it's a no-op on standalone / replica-set deployments.
				(new ShardingModel())->execute($db->getName(), $controller);
			}
		} catch (Exception $e) {
			$this->log($controller, 'DB initialization failed: ' . $e->getMessage());
			return false;
		}

		return true;
	}

	/**
	 * Create only the non-config collections and their indexes.
	 * Useful when the caller has already created the config collection
	 * (e.g. CreatetenantAction).
	 *
	 * @param Billrun_Db $db
	 * @param mixed      $controller optional controller for addOutput
	 */
	public function createCollections(Billrun_Db $db, $controller = null) {
		$existingCollections = $db->getCollectionNames();

		foreach ($this->getInitDbDefinition() as $item) {
			if (in_array($item['coll'], $existingCollections, true)) {
				continue;
			}
			$this->createCollectionWithIndexes($db, $item, $controller);
		}
	}

	/**
	 * Create the config collection (capped) and load base config if missing.
	 *
	 * @param Billrun_Db $db
	 * @param mixed      $controller optional controller for addOutput
	 */
	public function initConfigCollection(Billrun_Db $db, $controller = null) {
		$existingCollections = $db->getCollectionNames();

		if (in_array('config', $existingCollections, true)) {
			return;
		}
		$configCollection = $db->createCollection('config', ['capped' => true, 'size' => 104857600]);
		$this->log($controller, 'Created collection: config');
		$configCollection->createIndex(['urt' => -1], ['unique' => false, 'background' => true]);

		$configFile = $this->mongoBasePath . '/config.export';
		if (!file_exists($configFile)) {
			$this->log($controller, 'Warning: base config file not found at ' . $configFile);
			return;
		}
		$record = $this->loadExtendedJsonFile($configFile);
		if ($record === null) {
			$this->log($controller, 'Warning: could not parse base config file ' . $configFile);
			return;
		}
		$record['urt'] = new Mongodloid_Date();
		$db->getCollection('config')->insert($record);
		$this->log($controller, 'Added base config record');
	}

	/**
	 * Create a collection with optional parameters and indexes.
	 *
	 * @param Billrun_Db $db
	 * @param array      $item collection definition
	 * @param mixed      $controller optional controller for addOutput
	 */
	protected function createCollectionWithIndexes(Billrun_Db $db, array $item, $controller = null) {
		$params = isset($item['params']) ? $item['params'] : [];
		$collection = $db->createCollection($item['coll'], $params);
		$this->log($controller, 'Created collection: ' . $item['coll']);

		if (empty($item['indexes'])) {
			return;
		}
		foreach ($item['indexes'] as $idx) {
			$collection->createIndex($idx['fields'], isset($idx['params']) ? $idx['params'] : []);
		}
		$this->log($controller, 'Applied indexes for: ' . $item['coll']);
	}

	/**
	 * Load the first admin user from mongo/first_users.json into the users collection.
	 *
	 * If the BILLRUN_ADMIN_USER / BILLRUN_ADMIN_PASSWORD env vars are set and non-empty,
	 * they override the username and password (the password is hashed with password_hash).
	 *
	 * @param Billrun_Db $db
	 * @param mixed      $controller optional controller for addOutput
	 */
	public function loadFirstUser(Billrun_Db $db, $controller = null) {
		$usersFile = APPLICATION_PATH . '/mongo/first_users.json';
		if (!file_exists($usersFile)) {
			$this->log($controller, 'Warning: first user file not found at ' . $usersFile);
			return;
		}
		$record = $this->loadExtendedJsonFile($usersFile);
		if ($record === null) {
			$this->log($controller, 'Warning: could not parse first user file ' . $usersFile);
			return;
		}

		$envUser = getenv('BILLRUN_ADMIN_USER');
		if ($envUser !== false && $envUser !== '') {
			$record['username'] = $envUser;
		}
		$envPass = getenv('BILLRUN_ADMIN_PASSWORD');
		if ($envPass !== false && $envPass !== '') {
			$record['password'] = password_hash($envPass, PASSWORD_DEFAULT);
		}

		$db->getCollection('users')->insert($record);
		$this->log($controller, 'Added first user: ' . $record['username']);
	}

	/**
	 * Load default tax record from base file into the taxes collection.
	 *
	 * @param Billrun_Db $db
	 * @param mixed      $controller optional controller for addOutput
	 */
	public function loadBaseTaxes(Billrun_Db $db, $controller = null) {
		$taxFile = $this->mongoBasePath . '/taxes.export';
		if (!file_exists($taxFile)) {
			$this->log($controller, 'Warning: base taxes file not found at ' . $taxFile);
			return;
		}
		$record = $this->loadExtendedJsonFile($taxFile);
		if ($record === null) {
			$this->log($controller, 'Warning: could not parse base taxes file ' . $taxFile);
			return;
		}
		$db->getCollection('taxes')->insert($record);
		$this->log($controller, 'Added tax record for taxes collection');
	}

	/**
	 * Load and parse a MongoDB extended-JSON file, converting BSON type markers
	 * (e.g. {"$date": "..."}, {"$oid": "..."}) into native Mongodloid types.
	 *
	 * @param string $path
	 * @return array|null parsed document, or null on parse failure
	 */
	protected function loadExtendedJsonFile($path) {
		$raw = file_get_contents($path);
		if ($raw === false) {
			return null;
		}
		$decoded = json_decode($raw, true);
		if (!is_array($decoded)) {
			return null;
		}
		return $this->convertExtendedJson($decoded);
	}

	/**
	 * Recursively convert MongoDB extended-JSON markers in a decoded array to
	 * their native Mongodloid representations.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	protected function convertExtendedJson($value) {
		if (!is_array($value)) {
			return $value;
		}
		if (count($value) === 1 && array_key_exists('$date', $value)) {
			$date = $value['$date'];
			if (is_array($date) && isset($date['$numberLong'])) {
				$millis = (int) $date['$numberLong'];
				return new Mongodloid_Date(intdiv($millis, 1000), ($millis % 1000) * 1000);
			}
			if (is_numeric($date)) {
				$millis = (int) $date;
				return new Mongodloid_Date(intdiv($millis, 1000), ($millis % 1000) * 1000);
			}
			return new Mongodloid_Date(strtotime($date));
		}
		if (count($value) === 1 && array_key_exists('$oid', $value)) {
			return new Mongodloid_Id($value['$oid']);
		}
		foreach ($value as $key => $sub) {
			$value[$key] = $this->convertExtendedJson($sub);
		}
		return $value;
	}

	/**
	 * Collections / indexes definition (ported from mongo/create.js).
	 *
	 * @return array
	 */
	public function getInitDbDefinition() {
		return [
			[
				'coll' => 'lines',
				'indexes' => [
					['fields' => ['stamp' => 1], 'params' => ['unique' => true]],
					['fields' => ['urt' => 1], 'params' => ['unique' => false, 'sparse' => false, 'background' => true]],
					['fields' => ['type' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
					['fields' => ['sid' => 1, 'urt' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
					['fields' => ['aid' => 1, 'billrun' => 1, 'urt' => 1], 'params' => ['unique' => false, 'sparse' => false, 'background' => true]],
					['fields' => ['billrun' => 1, 'usaget' => 1, 'type' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
					['fields' => ['sid' => 1, 'session_id' => 1, 'request_num' => -1], 'params' => ['unique' => false, 'background' => true]],
					['fields' => ['session_id' => 1, 'request_num' => -1], 'params' => ['unique' => false, 'background' => true]],
					['fields' => ['sid' => 1, 'call_reference' => 1], 'params' => ['unique' => false, 'background' => true]],
					['fields' => ['call_reference' => 1, 'call_id' => 1], 'params' => ['unique' => false, 'background' => true]],
					['fields' => ['sid' => 1, 'billrun' => 1, 'urt' => 1], 'params' => ['unique' => false, 'sparse' => false, 'background' => true]],
				],
			],
			[
				'coll' => 'archive',
				'indexes' => [
					['fields' => ['stamp' => 1], 'params' => ['unique' => true]],
					['fields' => ['urt' => 1], 'params' => ['unique' => false, 'sparse' => false, 'background' => true]],
					['fields' => ['type' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
					['fields' => ['sid' => 1, 'urt' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
					['fields' => ['aid' => 1, 'urt' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
					['fields' => ['billrun' => 1, 'usaget' => 1, 'type' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
					['fields' => ['u_s' => 1], 'params' => ['unique' => false, 'background' => true]],
				],
			],
			[
				'coll' => 'log',
				'indexes' => [
					['fields' => ['stamp' => 1], 'params' => ['unique' => true]],
					['fields' => ['type' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
					['fields' => ['source' => 1], 'params' => ['unique' => false, 'sparse' => false, 'background' => true]],
					['fields' => ['start_process_time' => 1], 'params' => ['unique' => false, 'sparse' => false, 'background' => true]],
					['fields' => ['process_time' => 1], 'params' => ['unique' => false, 'sparse' => false, 'background' => true]],
					['fields' => ['received_time' => 1], 'params' => ['unique' => false, 'sparse' => false, 'background' => true]],
					['fields' => ['file_name' => 1], 'params' => ['unique' => false, 'sparse' => false, 'background' => true]],
				],
			],
			[
				'coll' => 'audit',
				'indexes' => [
					['fields' => ['stamp' => 1], 'params' => ['unique' => true]],
					['fields' => ['type' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
					['fields' => ['key' => 1], 'params' => ['unique' => false, 'sparse' => false, 'background' => true]],
					['fields' => ['collection' => 1], 'params' => ['unique' => false, 'sparse' => false, 'background' => true]],
					['fields' => ['urt' => 1], 'params' => ['unique' => false, 'sparse' => false, 'background' => true]],
					['fields' => ['user.name' => 1], 'params' => ['unique' => false, 'sparse' => false, 'background' => true]],
				],
			],
			[
				'coll' => 'rates',
				'indexes' => [
					['fields' => ['key' => 1, 'from' => 1, 'to' => 1], 'params' => ['unique' => true, 'background' => true]],
					['fields' => ['from' => 1, 'to' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
					['fields' => ['to' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
				],
			],
			[
				'coll' => 'queue',
				'indexes' => [
					['fields' => ['stamp' => 1], 'params' => ['unique' => true]],
					['fields' => ['calc_name' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
					['fields' => ['calc_time' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
					['fields' => ['type' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
					['fields' => ['aid' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
					['fields' => ['hash' => 1, 'calc_time' => 1, 'type' => 1], 'params' => ['background' => true]],
					['fields' => ['urt' => 1, 'type' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
				],
			],
			[
				'coll' => 'rebalance_queue',
				'indexes' => [
					['fields' => ['aid' => 1, 'billrun_key' => 1], 'params' => ['unique' => false, 'background' => true]],
					['fields' => ['creation_date' => 1, 'end_time' => 1], 'params' => ['unique' => false, 'background' => true]],
				],
			],
			[
				'coll' => 'users',
				'indexes' => [
					['fields' => ['username' => 1], 'params' => ['unique' => true, 'sparse' => true, 'background' => true]],
				],
			],
			[
				'coll' => 'billrun',
				'indexes' => [
					['fields' => ['invoice_id' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
					['fields' => ['invoice_date' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
					['fields' => ['aid' => 1, 'billrun_key' => 1], 'params' => ['unique' => true, 'background' => true]],
					['fields' => ['billrun_key' => -1, 'attributes.invoicing_day' => -1], 'params' => ['unique' => false, 'background' => true]],
				],
			],
			[
				'coll' => 'counters',
				'indexes' => [
					['fields' => ['coll' => 1, 'seq' => 1], 'params' => ['unique' => true, 'sparse' => false, 'background' => true]],
					['fields' => ['coll' => 1, 'key' => 1], 'params' => ['sparse' => false, 'background' => true]],
				],
			],
			[
				'coll' => 'billing_cycle',
				'indexes' => [
					['fields' => ['billrun_key' => 1, 'page_number' => 1, 'page_size' => 1], 'params' => ['unique' => true, 'background' => true]],
					['fields' => ['billrun_key' => 1, 'page_size' => 1, 'end_time' => 1], 'params' => ['unique' => false, 'sparse' => false, 'background' => true]],
					['fields' => ['billrun_key' => 1, 'page_size' => 1, 'count' => 1, 'invoicing_day' => 1], 'params' => ['unique' => false, 'sparse' => false, 'background' => true]],
				],
			],
			[
				'coll' => 'balances',
				'indexes' => [
					['fields' => ['aid' => 1, 'sid' => 1, 'from' => 1, 'to' => 1, 'priority' => 1], 'params' => ['unique' => true, 'background' => true]],
					['fields' => ['sid' => 1, 'from' => 1, 'to' => 1, 'priority' => 1], 'params' => ['background' => true]],
					['fields' => ['to' => 1], 'params' => ['background' => true]],
				],
			],
			[
				'coll' => 'prepaidincludes',
				'indexes' => [
					['fields' => ['external_id' => 1], 'params' => ['unique' => false]],
					['fields' => ['name' => 1], 'params' => ['unique' => false]],
					['fields' => ['from' => 1, 'to' => 1, 'name' => 1, 'external_id' => 1], 'params' => ['unique' => true]],
				],
			],
			[
				'coll' => 'prepaidgroups',
				'indexes' => [
					['fields' => ['name' => 1, 'from' => 1, 'to' => 1], 'params' => ['unique' => false, 'background' => true]],
					['fields' => ['name' => 1, 'to' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
					['fields' => ['description' => 1], 'params' => ['unique' => false, 'background' => true]],
				],
			],
			[
				'coll' => 'plans',
				'indexes' => [
					['fields' => ['name' => 1, 'from' => 1, 'to' => 1], 'params' => ['unique' => false, 'background' => true]],
					['fields' => ['name' => 1, 'to' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
					['fields' => ['description' => 1], 'params' => ['unique' => false, 'background' => true]],
				],
			],
			[
				'coll' => 'cards',
				'indexes' => [
					['fields' => ['serial_number' => 1], 'params' => ['unique' => false, 'background' => true]],
					['fields' => ['batch_number' => 1, 'serial_number' => 1], 'params' => ['unique' => true, 'background' => true]],
					['fields' => ['secret' => 1], 'params' => ['unique' => false, 'background' => true]],
					['fields' => ['from' => 1], 'params' => ['unique' => false, 'background' => true]],
					['fields' => ['to' => 1], 'params' => ['unique' => false, 'background' => true]],
				],
			],
			[
				'coll' => 'subscribers',
				'indexes' => [
					['fields' => ['aid' => 1], 'params' => ['unique' => false, 'sparse' => false, 'background' => true]],
					['fields' => ['aid' => 1, 'type' => 1, 'from' => 1, 'to' => 1], 'params' => ['unique' => false, 'sparse' => false, 'background' => true]],
					['fields' => ['invoicing_day' => 1], 'params' => ['unique' => false, 'sparse' => false, 'background' => true]],
					['fields' => ['sid' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
					['fields' => ['from' => 1, 'to' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
					['fields' => ['to' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
				],
			],
			[
				'coll' => 'subscribers_auto_renew_services',
				'indexes' => [
					['fields' => ['sid' => 1, 'from' => 1, 'to' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
					['fields' => ['from' => 1, 'to' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
					['fields' => ['to' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
					['fields' => ['next_renew_date' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
				],
			],
			[
				'coll' => 'statistics',
				'indexes' => [
					['fields' => ['creation_date' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
				],
			],
			[
				'coll' => 'services',
				'indexes' => [
					['fields' => ['name' => 1, 'from' => 1, 'to' => 1], 'params' => ['unique' => true, 'background' => true]],
					['fields' => ['name' => 1], 'params' => ['unique' => false]],
					['fields' => ['description' => 1], 'params' => ['unique' => false, 'background' => true]],
				],
			],
			[
				'coll' => 'events',
				'indexes' => [
					['fields' => ['creation_time' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
				],
			],
			['coll' => 'carriers', 'indexes' => []],
			[
				'coll' => 'collection_steps',
				'indexes' => [
					['fields' => ['trigger_date' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
					['fields' => ['extra_params.aid' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
				],
			],
			[
				'coll' => 'bills',
				'indexes' => [
					['fields' => ['aid' => 'hashed'], 'params' => ['unique' => false, 'background' => true]],
					['fields' => ['txid' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
					['fields' => ['invoice_id' => 1], 'params' => ['unique' => false, 'background' => true]],
					['fields' => ['billrun_key' => 1], 'params' => ['unique' => false, 'background' => true]],
					['fields' => ['invoice_date' => 1], 'params' => ['unique' => false, 'background' => true]],
					['fields' => ['urt' => 1], 'params' => ['unique' => false, 'background' => true]],
				],
			],
			[
				'coll' => 'discounts',
				'indexes' => [
					['fields' => ['key' => 1, 'from' => 1], 'params' => ['unique' => true, 'background' => true]],
					['fields' => ['from' => 1, 'to' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
					['fields' => ['to' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
				],
			],
			[
				'coll' => 'operations',
				'indexes' => [
					['fields' => ['action' => 1, 'filtration' => 1, 'start_time' => 1, 'end_time' => 1], 'params' => ['background' => true]],
					['fields' => ['action' => 1, 'end_time' => 1], 'params' => ['background' => true]],
					['fields' => ['action' => 1, 'filtration' => 1, 'lock_end_time' => 1, 'lock_expiry_time' => 1], 'params' => ['background' => true]],
					['fields' => ['lock_start_time' => 1], 'params' => ['expireAfterSeconds' => 5256000]],
					['fields' => ['start_time' => 1], 'params' => ['expireAfterSeconds' => 5256000]],
				],
			],
			['coll' => 'reports', 'indexes' => []],
			[
				'coll' => 'autorenew',
				'indexes' => [
					['fields' => ['from' => 1, 'to' => 1, 'next_renew' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
					['fields' => ['sid' => 1, 'aid' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
				],
			],
			[
				'coll' => 'taxes',
				'indexes' => [
					['fields' => ['key' => 1, 'from' => 1, 'to' => 1], 'params' => ['unique' => true, 'background' => true]],
					['fields' => ['from' => 1, 'to' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
					['fields' => ['to' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
				],
			],
			[
				'coll' => 'charges',
				'indexes' => [
					['fields' => ['key' => 1, 'from' => 1], 'params' => ['unique' => true, 'background' => true]],
					['fields' => ['from' => 1, 'to' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
					['fields' => ['to' => 1], 'params' => ['unique' => false, 'sparse' => true, 'background' => true]],
				],
			],
			[
				'coll' => 'suggestions',
				'indexes' => [
					['fields' => ['aid' => 1, 'sid' => 1, 'billrun_key' => 1, 'status' => 1, 'key' => 1, 'recalculation_type' => 1, 'estimated_billrun' => 1], 'params' => ['unique' => false, 'background' => true]],
					['fields' => ['status' => 1], 'params' => ['unique' => false, 'background' => true]],
				],
			],
			['coll' => 'oauth_clients', 'indexes' => [['fields' => ['client_id' => 1], 'params' => []]]],
			['coll' => 'oauth_access_tokens', 'indexes' => [['fields' => ['access_token' => 1], 'params' => []]]],
			['coll' => 'oauth_authorization_codes', 'indexes' => [['fields' => ['authorization_code' => 1], 'params' => []]]],
			['coll' => 'oauth_refresh_tokens', 'indexes' => [['fields' => ['refresh_token' => 1], 'params' => []]]],
			['coll' => 'oauth_users', 'indexes' => [['fields' => ['username' => 1], 'params' => []]]],
			['coll' => 'oauth_scopes', 'indexes' => [['fields' => ['oauth_scopes' => 1], 'params' => []]]],
			['coll' => 'oauth_jwt', 'indexes' => []],
			[
				'coll' => 'webhooks',
				'indexes' => [
					['fields' => ['webhook_id' => 1], 'params' => ['unique' => true, 'background' => true]],
					['fields' => ['module' => 1, 'action' => 1], 'params' => ['unique' => false, 'background' => true]],
				],
			],
			['coll' => 'jobs_queues', 'indexes' => []],
			[
				'coll' => 'jobs_messages',
				'indexes' => [
					['fields' => ['created' => 1], 'params' => ['unique' => false, 'background' => true, 'expireAfterSeconds' => 16070400]],
					['fields' => ['start_time' => 1], 'params' => ['unique' => false, 'background' => true]],
					['fields' => ['timeout' => 1], 'params' => ['unique' => false, 'background' => true]],
					['fields' => ['complete_time' => 1], 'params' => ['unique' => false, 'background' => true]],
					['fields' => ['schedule' => 1], 'params' => ['unique' => false, 'background' => true]],
					['fields' => ['handle' => 1], 'params' => ['unique' => false, 'background' => true]],
					['fields' => ['md5' => 1], 'params' => ['unique' => true, 'background' => true]],
					['fields' => ['queue_name' => 1, 'done' => 1, 'schedule' => 1, 'timeout' => 1], 'params' => ['unique' => false, 'background' => true]],
					['fields' => ['body.parent' => 1], 'params' => ['unique' => false, 'background' => true]],
					['fields' => ['body.type' => 1, 'created' => -1], 'params' => ['unique' => false, 'background' => true]],
				],
			],
		];
	}

	/**
	 * Emit a status line to the controller output when a controller is available.
	 *
	 * @param mixed  $controller
	 * @param string $message
	 */
	protected function log($controller, $message) {
		if ($controller && method_exists($controller, 'addOutput')) {
			$controller->addOutput($message);
		}
	}

}
