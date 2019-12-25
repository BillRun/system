<?php

/**
 * @package         Mongodloid
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
class Mongodloid_Collection {

	private $_collection;
	private $_db;

	const UNIQUE = 1;
	const DROP_DUPLICATES = 2;

	protected $w = 0;
	protected $j = false;

	public function __construct(MongoCollection $collection, Mongodloid_DB $db) {
		$this->_collection = $collection;
		$this->_db = $db;
	}

	public function update($query, $values, $options = array()) {
		if (!isset($options['w'])) {
			$options['w'] = $this->w;
		}
		if (!isset($options['j'])) {
			$options['j'] = $this->j;
		}
		return $this->_collection->update($query, $values, $options);
	}

	public function getName() {
		return $this->_collection->getName();
	}

	public function dropIndexes() {
		return $this->_collection->deleteIndexes();
	}

	public function dropIndex($field) {
		return $this->_collection->deleteIndex($field);
	}

	public function ensureUniqueIndex($fields, $dropDups = false) {
		return $this->ensureIndex($fields, $dropDups ? self::DROP_DUPLICATES : self::UNIQUE);
	}

	public function ensureIndex($fields, $params = array()) {
		if (!is_array($fields))
			$fields = array($fields => 1);

		$ps = array();
		if ($params == self::UNIQUE || $params == self::DROP_DUPLICATES)
			$ps['unique'] = true;
		if ($params == self::DROP_DUPLICATES)
			$ps['dropDups'] = true;

		// I'm so sorry :(
		if (Mongo::VERSION == '1.0.1')
			$ps = (bool) $ps['unique'];

		return $this->_collection->ensureIndex($fields, $ps);
	}

	public function getIndexedFields() {
		$indexes = $this->getIndexes();

		$fields = array();
		foreach ($indexes as $index) {
			$keys = array_keys($index->get('key'));
			foreach ($keys as $key)
				$fields[] = $key;
		}

		return $fields;
	}

	public function getIndexes() {
		$indexCollection = $this->_db->getCollection('system.indexes');
		return $indexCollection->query('ns', $this->_db->getName() . '.' . $this->getName());
	}

	public function query() {
		$query = new Mongodloid_Query($this);
		if (func_num_args()) {
			$query = call_user_func_array(array($query, 'query'), func_get_args());
		}
		return $query;
	}

	public function save(Mongodloid_Entity $entity, $w = null) {
		$data = $entity->getRawData();

		if (is_null($w)) {
			$w = $this->w;
		}

		$result = $this->_collection->save($data, array('w' => $w, 'j' => $this->j));
		if (!$result)
			return false;

		$entity->setRawData($data);
		return true;
	}

	public function findOne($id, $want_array = false) {
		if ($id instanceof Mongodloid_Id) {
			$filter_id = $id->getMongoId();
		} else if ($id instanceof MongoId) {
			$filter_id = $id;
		} else {
			// probably a string
			$filter_id = new MongoId((string) $id);
		}

		$values = $this->_collection->findOne(array('_id' => $filter_id));

		if ($want_array)
			return $values;

		return new Mongodloid_Entity($values, $this);
	}

	public function drop() {
		return $this->_collection->drop();
	}

	public function count() {
		return $this->_collection->count();
	}

	public function clear() {
		return $this->remove(array());
	}

	public function remove($query, $options = array('w' => 1)) {
		// avoid empty database
		if (empty($query)) {
			return false;
		}
		
		if ($query instanceOf Mongodloid_Entity)
			$query = $query->getId();

		if ($query instanceOf Mongodloid_Id)
			$query = array('_id' => $query->getMongoId());

		return $this->_collection->remove($query, $options);
	}

	/**
	 * @return MongoCursor a cursor for the search results.
	 */
	public function find($query, $fields = array()) {
		return $this->_collection->find($query, $fields);
	}

	public function aggregate() {
		$args = func_get_args();
		$timeout = $this->getTimeout();
		$this->setTimeout(-1);
		$result = call_user_func_array(array($this->_collection, 'aggregate'), $args);
		$this->setTimeout($timeout);
		if (!isset($result['ok']) || !$result['ok']) {
			throw new Mongodloid_Exception('aggregate failed with the following error: ' . $result['code'] . ' - ' . $result['errmsg']);
			return false;
		}
		return $result['result'];
	}

	public function aggregatecursor() {
		$args = func_get_args();
		// on 2.4 and below use old aggregate
		if (!$this->_db->compareServerVersion('2.6', '>=') || !$this->_db->compareClientVersion('1.6', '>=')) { // TODO Need to update Mongodloid_Cursor functions
			throw new Exception('Aggregate cursor support MongoDB 2.6+ and PHP MongoDB client 1.6+');
		}
		// on 2.6 and above it's much more simple
		if (count($args)>1) { // Assume the array contains 'ops' for backward compatibility
			$args = array($args);
		}
		return new Mongodloid_Cursor(call_user_func_array(array($this->_collection, 'aggregateCursor'), $args));

	}
	public function setTimeout($timeout) {
		if ($this->_db->compareClientVersion('1.5.3', '<')) {
			@MongoCursor::$timeout = (int) $timeout;
		} else {
			// see bugs:
			// https://jira.mongodb.org/browse/PHP-1099
			// https://jira.mongodb.org/browse/PHP-1080
		}
	}

	public function getTimeout() {
		return MongoCursor::$timeout;
	}

	/**
	 * method to set read preference of collection connection
	 * 
	 * @param string $readPreference The read preference mode: RP_PRIMARY, RP_PRIMARY_PREFERRED, RP_SECONDARY, RP_SECONDARY_PREFERRED or RP_NEAREST
	 * @param array $tags An array of zero or more tag sets, where each tag set is itself an array of criteria used to match tags on replica set members
	 * 
	 * @return boolean TRUE on success, or FALSE otherwise.
	 */
	public function setReadPreference($readPreference, array $tags = array()) {
		if (defined('MongoClient::' . $readPreference)) {
			$this->_collection->setReadPreference(constant('MongoClient::' . $readPreference), $tags);
		} else if (in_array($readPreference, Mongodloid_Connection::$availableReadPreferences)) {
			$this->_collection->setReadPreference($readPreference, $tags);
		}
		return $this;
	}

	/**
	 * method to load Mongo DB reference object
	 * 
	 * @param MongoDBRef $ref the reference object
	 * 
	 * @return array
	 */
	public function getRef($ref) {
		if (!MongoDBRef::isRef($ref)) {
			return;
		}
		if (!($ref['$id'] instanceof MongoId)) {
			$ref['$id'] = new MongoId($ref['$id']);
		}
		return new Mongodloid_Entity($this->_collection->getDBRef($ref));
	}

	/**
	 * method to create Mongo DB reference object
	 * 
	 * @param array $a raw data of object to create reference to itself; later on you can use the return value to store in other collection
	 * 
	 * @return MongoDBRef
	 */
	public function createRef($a) {
		return $this->_collection->createDBRef($a);
	}

	/**
	 * Update a document and return it
	 * 
	 * @param array $query The query criteria to search for
	 * @param array $update The update criteria
	 * @param array $fields Optionally only return these fields
	 * @param array $options An array of options to apply, such as remove the match document from the DB and return it
	 * @param boolean $asCommand On some mongodb versions FAM is not working well and FAM command better to use
	 * 
	 * @return Mongodloid_Entity the original document, or the modified document when new is set.
	 * @throws MongoResultException on failure
	 * @see http://php.net/manual/en/mongocollection.findandmodify.php
	 */
	public function findAndModify(array $query, array $update = array(), array $fields = null, array $options = array(), $asCommand = false) {
		if (!$asCommand) {
			$ret = $this->_collection->findAndModify($query, $update, $fields, $options);
		} else {
			$commandOptions = array(
				'findAndModify' => $this->getName(),
				'query' => $query,
				'update' => $update,
				'fields' => $fields,
			);
			$ret = $this->_db->command(array_merge($commandOptions, $options));
		}

		return new Mongodloid_Entity($ret, $this);
	}

	/**
	 * method to bulk insert of multiple documents
	 * 
	 * @param array $a array or object. If an object is used, it may not have protected or private properties
	 * @param array $options options for the inserts.; see php documentation
	 * 
	 * @return mixed If the w parameter is set to acknowledge the write, returns an associative array with the status of the inserts ("ok") and any error that may have occurred ("err"). Otherwise, returns TRUE if the batch insert was successfully sent, FALSE otherwise
	 * @see http://php.net/manual/en/mongocollection.batchinsert.php
	 */
	public function batchInsert(array $a, array $options = array()) {
		if (!isset($options['w'])) {
			$options['w'] = $this->w;
		}

		if (!isset($options['j'])) {
			$options['j'] = $this->j;
		}

		if ($this->_db->compareServerVersion('2.6', '>=') && $this->_db->compareClientVersion('1.5', '>=')) {
			$batch = new MongoInsertBatch($this->_collection);
			foreach($a as $doc) {
				$batch->add($doc);
			}
			return $batch->execute($options);
		} else {
			return $this->_collection->batchInsert($a, $options);
		}
		
	}

	/**
	 * method to insert document
	 * 
	 * @param array $a array or object. If an object is used, it may not have protected or private properties
	 * @param array $options the options for the insert; see php documentation
	 * 
	 * @return mixed Returns an array containing the status of the insertion if the "w" option is set. Otherwise, returns TRUE if the inserted array is not empty
	 * @see http://www.php.net/manual/en/mongocollection.insert.php
	 */
	public function insert($a, array $options = array()) {
		if (!isset($options['w'])) {
			$options['w'] = $this->w;
		}
		
		if (!isset($options['j'])) {
			$options['j'] = $this->j;
		}

		return $this->_collection->insert( ($a instanceof Mongodloid_Entity ? $a->getrawData() : $a), $options);
	}

	/**
	 * Method to create auto increment of document
	 * To use this method require counters collection (see create.ini)
	 * 
	 * @param string $id the id of the document to auto increment
	 * @param int $init_id the first value if no value exists
	 * 
	 * @return int the incremented value
	 */
	public function createAutoInc($oid, $init_id = 1, $collName = FALSE) {

		$countersColl = $this->_db->getCollection('counters');
		$collection_name = !empty($collName) ? $collName : $this->getName();

		// try to set last seq
		while (1) {
			// get last seq
			$lastSeq = $countersColl->query('coll', $collection_name)->cursor()->sort(array('seq' => -1))->limit(1)->current()->get('seq');
			if (is_null($lastSeq)) {
				$lastSeq = $init_id;
			} else {
				$lastSeq++;
			}
			$insert = array(
				'coll' => $collection_name,
				'oid' => $oid,
				'seq' => $lastSeq
			);

			try {
				$ret = $countersColl->insert($insert, array('w' => 1));
			} catch (MongoCursorException $e) {
				if ($e->getCode() == 11000) {
					// duplicate - need to check if oid already exists
					$ret = $this->getAutoInc($oid);
					if (empty($ret) || !is_numeric($ret)) {
						// if oid not exists - probably someone insert same seq at the same time
						// let's try to insert same oid with next seq
						continue;
					}
					$lastSeq = $ret;
					break;
				}
			}
			break;
		}
		return $lastSeq;
	}

	public function getAutoInc($oid) {
		$countersColl = $this->_db->getCollection('counters');
		$collection_name = $this->getName();
		$query = array(
			'coll' => $collection_name,
			'oid' => $oid,
		);
		return $countersColl->query($query)->cursor()->limit(1)->current()->get('seq');
	}
	
	/**
	 * 
	 * @return MongoCollection
	 */
	public function getMongoCollection() {
		return $this->_collection;
	}
	/**
	 * method to get collection stats
	 * 
	 * @param mixed $item return only specific property of stats
	 * 
	 * @return mixed the whole stats or just one item of it
	 */
	public function stats($item) {
		return $this->_db->stats(array('collStats' => $this->getName()), $item);
	}
	
	public function getWriteConcern($var = null) {
		// backward compatibility with 1.4
		if ($this->_db->compareClientVersion('1.5', '<')) {
			$ret = array(
				'w' => $this->w,
				'wtimeout' => $this->getTimeout(),
			);
		} else {
			$ret = $this->_collection->getWriteConcern();
		}
		
		if (is_null($var)) {
			return $ret;
		}
		
		if (isset($ret[$var])) {
			return $ret[$var];
		}
	}
	
	public function distinct($key, array $query = null) {
		return $this->_collection->distinct($key, $query);
	}

}
