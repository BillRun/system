<?php

/**
 * @package         Mongodloid
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
class Mongodloid_Db {

	protected $_db;
	protected $_connection;
	protected $_collections = array();
	const VERSION = '1.6.12';

	public function __construct(MongoDB\Database $db, Mongodloid_Connection $connection) {
		$this->_db = $db;
		$this->_connection = $connection;
	}

	/**
	 * method to return the collection instance
	 * 
	 * @param string $name collection name
	 * 
	 * @return Mongodloid_Collection
	 */
	public function getCollection($name) {
		if (!isset($this->_collections[$name]) || !$this->_collections[$name]) {
			$this->_collections[$name] = new Mongodloid_Collection($this->_db->selectCollection($name, ['codec' => null]), $this);
		}

		return $this->_collections[$name];
	}

	public function getName() {
		return (string) $this->_db->getDatabaseName();
	}

	public function command(array $command, array $options = array()) {
		return $this->_db->command($command, $options);
	}

	/**
	 * Run a database command against a *different* database than this
	 * connection's bound DB, using the same auth context.
	 *
	 * Internal escape hatch backing the semantic admin methods on this class
	 * (createUser, enableSharding, shardCollection, reshardCollection, ...).
	 * Kept protected so the application layer goes through the semantic
	 * methods rather than constructing raw MongoDB command documents.
	 *
	 * @param string $targetDbName database to route the command to
	 * @param array  $command      MongoDB command document
	 * @param array  $options      driver options (see MongoDB\Driver\Manager::executeCommand)
	 * @return MongoDB\Driver\Cursor
	 */
	protected function commandOn($targetDbName, array $command, array $options = array()) {
		$cmd = new MongoDB\Driver\Command($command);
		return $this->_db->getManager()->executeCommand($targetDbName, $cmd, $options);
	}

	/**
	 * Enable sharding on a database (cluster-admin operation).
	 *
	 * Equivalent to mongosh's `sh.enableSharding(dbName)`. Routes the
	 * underlying enableSharding command to the `admin` database via the
	 * current connection's auth context.
	 *
	 * @param string $dbName database to enable sharding on
	 * @return MongoDB\Driver\Cursor
	 */
	public function enableSharding($dbName) {
		return $this->commandOn('admin', array('enableSharding' => $dbName));
	}

	/**
	 * Shard a collection with the given shard key (cluster-admin operation).
	 *
	 * Equivalent to mongosh's `sh.shardCollection(namespace, key)`.
	 *
	 * @param string $namespace fully-qualified collection name, e.g. 'mydb.lines'
	 * @param array  $shardKey  e.g. ['stamp' => 1] or ['aid' => 'hashed']
	 * @return MongoDB\Driver\Cursor
	 */
	public function shardCollection($namespace, array $shardKey) {
		return $this->commandOn('admin', array(
			'shardCollection' => $namespace,
			'key' => $shardKey,
		));
	}

	/**
	 * Re-shard an existing collection with a new shard key (MongoDB >= 5.0).
	 *
	 * Equivalent to mongosh's `sh.reshardCollection(namespace, key)`.
	 *
	 * Note: the underlying reshardCollection command kicks off an
	 * asynchronous data-movement job; this method returns as soon as the
	 * coordinator accepts the request, not when resharding completes.
	 *
	 * @param string $namespace   fully-qualified collection name
	 * @param array  $newShardKey new shard key spec
	 * @return MongoDB\Driver\Cursor
	 */
	public function reshardCollection($namespace, array $newShardKey) {
		return $this->commandOn('admin', array(
			'reshardCollection' => $namespace,
			'key' => $newShardKey,
		));
	}

	/**
	 * Create a database user via MongoDB's createUser command.
	 *
	 * The command is routed to $targetDbName using the current connection's
	 * auth context, so the user record lands in $targetDbName.system.users
	 * (auth-db = $targetDbName). This is the PHP equivalent of mongosh's
	 *   db.getSiblingDB($targetDbName).createUser({...})
	 *
	 * @param string $username
	 * @param string $password plain text; MongoDB hashes server-side
	 * @param string|array $roles either a single role name (which is expanded
	 *                            to [{role: $roles, db: $targetDbName}]) or an
	 *                            array of role specs in MongoDB's native form,
	 *                            e.g. [['role' => 'readWrite', 'db' => 'foo']]
	 * @param string|null $targetDbName database to create the user in;
	 *                                  defaults to this connection's bound DB
	 * @return MongoDB\Driver\Cursor
	 */
	public function createUser($username, $password, $roles, $targetDbName = null) {
		if ($targetDbName === null) {
			$targetDbName = $this->getName();
		}
		if (is_string($roles)) {
			$roles = array(array('role' => $roles, 'db' => $targetDbName));
		}
		return $this->commandOn($targetDbName, array(
			'createUser' => $username,
			'pwd' => $password,
			'roles' => $roles,
		));
	}

	/**
	 * method to get dbStats or collection stats (for the later see the stats method in collection class)
	 * 
	 * @param array $stats which stats to pull
	 * @param mixed $item return only specific property of stats
	 * 
	 * @return mixed the whole stats or just one item of it
	 */
	public function stats(array $stats = array('dbStats' => 1), $item = null) {
		$ret = Mongodloid_Result::getResult(iterator_to_array($this->_db->command($stats))[0]);

		if (is_null($item)) {
			return $ret;
		}

		if (isset($ret[$item])) {
			return $ret[$item];
		}
	}

	/**
	 * method to copmare version of mongo (server or client)
	 * 
	 * @param string $compare compare to version number
	 * @param string $source what version to compare to (server or client)
	 * @param string $operator operator how to compare (see PHP version_compare function)
	 * 
	 * @return mixed see PHP documentation of version_compare function
	 */
	protected function compareMongoVersion($compare, $source = 'server', $operator = null) {
		if (strtolower($source) == 'server') {
			$version = $this->getServerVersion();
		} else {
			$version = $this->getClientVersion();
		}

		if (!empty($operator)) {
			return version_compare($version, $compare, $operator);
		}

		return version_compare($version, $compare);
	}

	/**
	 * method to get mongodb server version
	 * 
	 * @param string $compare compare to version number
	 * @param string $operator operator how to compare (see PHP version_compare function)
	 * 
	 * @return mixed see PHP documentation of version_compare function
	 */
	public function compareServerVersion($compare, $operator = null) {
		return $this->compareMongoVersion($compare, 'server', $operator);
	}

	/**
	 * method to get mongodb server version
	 * 
	 * @param string $compare compare to version number
	 * @param string $operator operator how to compare (see PHP version_compare function)
	 * 
	 * @return mixed see PHP documentation of version_compare function
	 */
	public function compareClientVersion($compare, $operator = null) {
		return $this->compareMongoVersion($compare, 'client', $operator);
	}

	/**
	 * method to get mongodb client version
	 * 
	 * @return string version
	 */
	public function getClientVersion() {
		return Mongodloid_Db::VERSION;
	}

	/**
	 * method to get mongodb server version
	 * 
	 * @return string version
	 */
	public function getServerVersion() {
		$mongodb_info = Mongodloid_Result::getResult(iterator_to_array($this->_db->command(array('buildinfo' => true)))[0]);
		return $mongodb_info['version'];
	}
	
	/**
	 * Change the default number size in mongo to long or regular (64/32 bit) size.
	 * @param int $status either 1 to turn on or 0 for off
	 * @deprecated since version 4.0
	 */
	public function setMongoNativeLong($status = 1) {
		if ($status == 0 && $this->compareServerVersion('2.6', '>=') === true) {
			return;
		}
		ini_set('mongo.native_long', $status);
	}

	/**
	 * method to start session and retrieve it
	 * 
	 * @return MongoDB\Driver\Session
	 */
	public function startSession() {
		if ($this->isStandalone()) {
			return false;
		}
		return $this->_connection->startSession();
	}

	/**
	 * method to get the mongo servers
	 *
	 * @return MongoDB\Driver\Session
	 */
	public function getServers() {
		return $this->_connection->getServers();
	}
	
	/**
	 * method to check if the db server is standalone (not mongos and not replica-set)
	 * 
	 * @return boolean true if standalone else false
	 */
	public function isStandalone() {
		$servers = $this->getServers();
		return $servers[0]->getType() === MongoDB\Driver\Server::TYPE_STANDALONE;
	}
	
	/**
	 * method to check if the db server is cluster (mongos)
	 * 
	 * @return boolean true if cluster else false
	 */
	public function isCluster() {
		$servers = $this->getServers();
		return $servers[0]->getType() === MongoDB\Driver\Server::TYPE_MONGOS;
	}
	
	/**
     * Fetches toolkit for dealing with files stored in this database
     *
     * @param string $prefix The prefix for the files and chunks collections.
     * @return Mongodloid_GridFS Returns a new gridfs object for this database.
     */
    public function getGridFS($prefix = "fs")
    {
        return new Mongodloid_GridFS($this, $prefix);
    }

	/**
	 * Get the current MongoDB\Database
	 * @return MongoDB\Database
	 */
	public function getDb() {
		return $this->_db;
	}

	/**
	 * Get the names of all collections in the database
	 *
	 * @return string[]
	 */
	public function getCollectionNames() {
		$names = [];
		foreach ($this->_db->listCollections() as $collInfo) {
			$names[] = $collInfo->getName();
		}
		return $names;
	}

	/**
	 * Create a new collection in the database
	 *
	 * @param string $name collection name
	 * @param array $options collection options (e.g. capped, size)
	 *
	 * @return Mongodloid_Collection
	 * @see https://docs.mongodb.com/php-library/current/reference/method/MongoDBDatabase-createCollection/
	 */
	public function createCollection($name, array $options = array()) {
		$this->_db->createCollection($name, $options);
		return $this->getCollection($name);
	}

	/**
	 * Check if the database environment supports transactions.
	 * Transactions require MongoDB 4.2+ and a Replica Set or Sharded Cluster (not standalone).
	 *
	 * @return boolean
	 */
	public function supportsTransactions()
	{
		return $this->compareServerVersion('4.2.0', '>=') && !$this->isStandalone();
	}
}
