<?php

/**
 * @package         Mongodloid
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
class Mongodloid_Collection {

	private $_collection;
	private $_db;

	const UNIQUE = 1;
	const DROP_DUPLICATES = 2;

	protected $w = 1;
	protected $j = false;

	/**
	 * Create a new instance of the collection object.
	 * @param MongoCollection $collection
	 * @param Mongodloid_DB $db
	 */
	public function __construct(MongoCollection $collection, Mongodloid_DB $db) {
		$this->_collection = $collection;
		$this->_db = $db;
	}

	/**
	 * Update a collection.
	 * @param type $query - Query to filter records to update.
	 * @param type $values - Query for updating new values.
	 * @param type $options - Mongo options.
	 * @return mongo update result.
	 */
	public function update($query, $values, $options = array()) {
		if ((isset($options['session']) && $options['session']->isInTransaction())) {
			$options['wTimeoutMS'] = $options['wTimeoutMS'] ?? $this->getTimeout();
		} else if (!isset($options['w'])) {
			$options['w'] = $this->w;
		}
		return $this->_collection->update($query, $values, $options);
	}

	/**
	 * Set the fields into the entity.
	 * @param Mongodloid_Entity - Entity to set values into.
	 * @param type $fields - Fields to set.
	 */
	protected function setEntityFields($entity, $fields) {
		foreach ($fields as $key => $value) {
			// Set values.
			$entity->set($key, $value);
		}
	}
	
	/**
	 * Update a collection by entity.
	 * @param Mongodloid_Entity $entity - Entity to update in the collection.
	 * @param array $fields - Array of keys and values to be updated in the entity.
	 * @return mongo update result.
	 */
	public function updateEntity($entity, $fields=array()) {
		if (empty($fields)) {
			$fields = $entity->getRawData();
			unset($fields['_id']);
		}
		
		$data = array(
			'_id' => $entity->getId()->getMongoID()
		);
		
		// This function changes fields, should I clone fields before sending?
		$this->setEntityFields($entity, $fields);
		
		return $this->update($data, array('$set' => $fields));
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

	/**
	 * Create a query instance based on the current collection.
	 * @return Mongodloid_Query
	 */
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

		$options = array('w' => $w);
		
		if ($this->_db->compareServerVersion('3.4', '<') && !extension_loaded('mongodb')) {
			$options['j'] = $this->j;
		}

		$result = $this->_collection->save($data, $options);
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
	
	/**
	 * Remove an entity from the collection.
	 * @param Mongoldoid_Entity $entity - Entity to remove from the collection.
	 * @param array $options - Options to send to the mongo
	 * @return boolean true if succssfull.
	 */
	public function removeEntity($entity, $options = array('w' => 1)) {
		$query = $entity->getId();
		return $this->remove($query, $options);
	}
	
	/**
	 * Remove an entity from the collection.
	 * @param Mongoldoid_Entity $id - ID of mongo record to be removed.
	 * @param array $options - Options to send to the mongo
	 * @return boolean true if succssfull.
	 */
	public function removeId($id, $options = array('w' => 1)) {
		$query = array('_id' => $id->getMongoId());
		return $this->remove($query, $options);
	}
	
	/**
	 * Remove data from the collection.
	 * @param Query $query - Query object or Mongoldoid_Entity to use to remove data.
	 * @param array $options - Options to send to the mongo
	 * @return boolean true if succssfull.
	 */
	public function remove($query, $options = array('w' => 1)) {
		// avoid empty database
		if (empty($query)) {
			return false;
		}
		
		// TODO: Remove this conditions and use removeEntity and removeId instead.
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
//		$cursor = $this->_collection->find($query, $fields);
//		return $mongoResult? $cursor : new Mongodloid_Cursor($cursor);
	}

	/**
	 * Check if a certain entity exists in the collection.
	 * @return boolean true if the query returned results.
	 */
	public function exists($query) {
		if(!$query) {
			return false;
		}
		
		$cursor = $this->query($query)->cursor();
		// TODO: Validation on everything.
		return !$cursor->current()->isEmpty();
	}
	
	/**
	 * 
	 * @deprecated since version 4.0 - backward compatibility
	 */
	public function aggregatecursor() {
		$args = func_get_args();
		return $this->aggregate($args);
	}

	public function aggregate() {
		$args = func_get_args();
		if (count($args)>1) { // Assume the array contains 'ops' for backward compatibility
			$args = array($args);
		}
		return new Mongodloid_Cursor(call_user_func_array(array($this->_collection, 'aggregateCursor'), $args));
	}

	

	public function aggregateWithOptions() {
            $args = func_get_args();
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
	 * @param Mongodloid_Entity $entity Entity to create ref by.
	 * 
	 * @return MongoDBRef
	 */
	public function createRefByEntity($entity) {
		// TODO: Validate the entity?
		if (is_array($entity)) {
			$refData = $entity;
		} else if ($entity instanceof Mongodloid_Entity) {
			$refData = $entity->getRawData();
		} else {
			return false;
		}
		return $this->_collection->createDBRef($refData);
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
	 * @param boolean $retEntity return Mongodloid entity instead of native return of FindAndModify
	 * 
	 * @return Mongodloid_Entity the original document, or the modified document when new is set.
	 * @throws MongoResultException on failure
	 * @see http://php.net/manual/en/mongocollection.findandmodify.php
	 */
	public function findAndModify(array $query, array $update = array(), array $fields = null, array $options = array(), $retEntity = true) {
		$ret = $this->_collection->findAndModify($query, $update, $fields, $options);

		if ($retEntity) {
			return new Mongodloid_Entity($ret, $this);
		}
		return $ret;
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

		if (!isset($options['j']) && $this->_db->compareServerVersion('3.4', '<') && !extension_loaded('mongodb')) {
			$options['j'] = $this->j;
		}

		if ($this->_db->compareServerVersion('2.6', '>=') && $this->_db->compareClientVersion('1.5', '>=')) {
			$batch = new MongoInsertBatch($this->_collection);
			foreach($a as $doc) {
				if ($doc instanceof Mongodloid_Entity) {
					$doc = $doc->getRawData();
				}
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
	 * @param array $ins array or object. If an object is used, it may not have protected or private properties
	 * @param array $options the options for the insert; see php documentation
	 * 
	 * @return mixed Returns an array containing the status of the insertion if the "w" option is set. Otherwise, returns TRUE if the inserted array is not empty
	 * @see http://www.php.net/manual/en/mongocollection.insert.php
	 */
	public function insert(&$ins, array $options = array()) {
		if (!isset($options['w'])) {
			$options['w'] = $this->w;
		}
		
		if (!isset($options['j']) && $this->_db->compareServerVersion('3.4', '<') && !extension_loaded('mongodb')) {
			$options['j'] = $this->j;
		}
		if ($ins instanceof Mongodloid_Entity) {
			$a = $ins->getRawData();
			$ret = $this->_collection->insert($a , $options);
			$ins = new Mongodloid_Entity($a);
		} else {
			$a = $ins; // pass by reference - _id is not saved on by-ref var
			$ret = $this->_collection->insert($a , $options);
			$ins = $a;
		}
		return $ret;
	}

	/**
	 * Method to create auto increment of document based on an entity.
	 * To use this method require counters collection (see create.ini)
	 * 
	 * @param Mongodloid_Entity $entity - Entity to create auto inc for.
	 * @param string $field the field to set the auto increment
	 * @param int $min_id the default value to use for the first value
	 * 
	 * @return mixed the auto increment value or void on error
	 */
	public function createAutoIncForEntity($entity, $field, $min_id = 1) {
		// check if already set auto increment for the field
		$value = $entity->get($field);
		if ($value) {
			return $value;
		}

		// check if id exists (cannot create auto increment without id)
		$id = $entity->getId();
		if (!$id) {
			Billrun_Factory::log("createAutoIncForEntity no id.");
			// TODO: Report error?
			return;
		}

		$inc = $this->createAutoInc($id->getMongoID(), $min_id);
		
		// Set the values to the entity.
		$entity->set($field, $inc);
		return $inc;
	}
	/**
	 * Method to create auto increment of document
	 * To use this method require counters collection (see create.ini)
	 * 
	 * @param mixed $params the id of the document to auto increment
	 * @param int $init_id the first value if no value exists
	 * 
	 * @return int the incremented value
	 */
	public function createAutoInc($params = null, $init_id = 1, $collName = FALSE) {

		$countersColl = $this->_db->getCollection('counters');
		$collection_name = !empty($collName) ? $collName : $this->getName();
		//check for existing seq
		if (!empty($params)) {
			$key = serialize($params);
			$existingSec = $countersColl->query(array('coll' => $collection_name, 'key' => $key))->cursor()->setReadPreference('RP_PRIMARY')->limit(1)->current()->get('seq');
			if (!is_null($existingSec)) {
				return $existingSec;
			}
		}
			
		// try to set last seq
		while (1) {
			// get last seq
			$lastSeq = $countersColl->query('coll', $collection_name)->cursor()->setReadPreference('RP_PRIMARY')->sort(array('seq' => -1))->limit(1)->current()->get('seq');
			if (is_null($lastSeq) || $lastSeq < $init_id) {
				$lastSeq = $init_id;
			} else {
				$lastSeq++;
			}
			$insert = array(
				'coll' => $collection_name,
				'seq' => $lastSeq
			);
			
			if (!empty($params)) {
				$insert['key'] = $key;
			}
			
			try {
				$ret = $countersColl->insert($insert, array('w' => 1));
			} catch (MongoException $e) {
				if (in_array($e->getCode(), Mongodloid_General::DUPLICATE_UNIQUE_INDEX_ERROR)) {
					// try again with the next seq
					continue;
				}
			}
			break;
		}
		return $lastSeq;
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
	
	public function distinct($key, array $query = array()) {
		return $this->_collection->distinct($key, $query);
	}

}
