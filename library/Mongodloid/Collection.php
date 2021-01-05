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
	 * @param MongoDB\Collection $collection
	 * @param Mongodloid_DB $db
	 */
	public function __construct(MongoDB\Collection $collection, Mongodloid_DB $db) {
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

		$multiple = isset($options['multiple']) ? $options['multiple'] : false;
		$isReplace = !\MongoDB\is_first_key_operator($values);

		if ($isReplace && $multiple) {
			throw new Exception('multi update only works with $ operators', 9);
		}
		unset($options['multiple']);
		$this->convertWriteConcernOptions($options);
		$query = self::fromMongodloid($query);
		if ($isReplace) {
			$res = $this->replaceOne($query, $values, $options);
		} else if ($multiple) {
			$res =  $this->updateMany($query, $values, $options);
		} else {
			$res = $this->updateOne($query, $values, $options);
		}
		return self::getResult($res);
	}

	/**
	 * Update all documents that match the query.
	 * @param type $query - Query to filter records to update
	 * @param type $values - Query for updating new values.
	 * @param type $options - Mongo options.
	 * @return mongodb updateMany result
	 */
	private function updateMany($query, $values, $options = array()) {
		return $this->_collection->updateMany($query, $values, $options);
	}

	/**
	 * Update at most one document that matches the query. 
	 * If multiple documents match the query,
	 * only the first matching document will be updated.
	 * @param type $query - Query to filter record to update
	 * @param type $values - Query for updating new values.
	 * @param type $options - Mongo options.
	 * @return mongodb updateOne result
	 */
	private function updateOne($query, $values, $options = array()) {
		return $this->_collection->updateOne($query, $values, $options);
	}

	/**
	 * Replace at most one document that matches the query. 
	 * If multiple documents match the query, 
	 * only the first matching document will be replaced.
	 * @param type $query- Query to filter record to replace
	 * @param type $values - new values.
	 * @param type $options - Mongo options.
	 * @return mongodb replaceOne result
	 */
	private function replaceOne($query, $values, $options = array()) {
		return $this->_collection->replaceOne($query, $values, $options);
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
	public function updateEntity($entity, $fields = array()) {
		if (empty($fields)) {
			$fields = $entity->getRawData();
			unset($fields['_id']);
		}

		$data = array(
			'_id' => $entity->getId()->getMongoID()
		);

		// This function changes fields, should I clone fields before sending?
		$this->setEntityFields($entity, $fields);

		return $this->buildUpdateResult($this->updateOne($data, array('$set' => $fields)));
	}

	public function getName() {
		return self::getResult($this->_collection->getCollectionName());
	}

	public function dropIndexes() {
		return self::getResult($this->_collection->dropIndexes());
	}

	public function dropIndex($field) {
		return self::getResult($this->_collection->dropIndex($field));
	}

	public function ensureUniqueIndex($fields, $dropDups = false) {
		return $this->ensureIndex($fields, $dropDups ? self::DROP_DUPLICATES : self::UNIQUE);
	}

	public function ensureIndex($fields, $params = array()) {
		if (!is_array($fields)) {
			$fields = array($fields => 1);
		}
		$ps = array();
		if ($params == self::UNIQUE || $params == self::DROP_DUPLICATES) {
			$ps['unique'] = true;
		}
		return self::getResult($this->_collection->createIndex($fields, $ps));
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
		return self::getResult($this->_collection->listIndexes());
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
		$options['upsert'] = true;
		$options['w'] = $w;
		$this->convertWriteConcernOptions($options);
		$data = $entity->getRawData();
		if($entity->getId()){
			$id = $entity->getId()->getMongoID();
		}else{
			$id = (new Mongodloid_Id())->getMongoID();
			$data['_id'] =  $id;
		}
		
		$result = $this->replaceOne(array('_id' => $id), $data, $options);
		if (!$result) {
			return false;
		}
		$entity->setRawData($data);
		return true;
	}

	public function findOne($id, $want_array = false) {
		$values = self::getResult($this->_collection->findOne(array('_id' => self::fromMongodloid($id))));

		if ($want_array) {
			return $values;
		}
		return new Mongodloid_Entity($values, $this);
	}

	public function drop() {
		return self::getResult($this->_collection->drop());
	}

	public function count() {
		return self::getResult($this->_collection->count());
	}

	public function clear() {//TODO:: check this - I dont think this works also before changes
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
	 * @param Mongoldoid_Id $id - ID of mongo record to be removed.
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
		$multiple = isset($options['justOne']) ? !$options['justOne'] : true;
		unset($options['justOne']);
		// TODO: Remove this conditions and use removeEntity and removeId instead.
		if ($query instanceOf Mongodloid_Entity)
			$query = $query->getId();

		if ($query instanceOf Mongodloid_Id)
			$query = array('_id' => $query->getMongoId());

		$this->convertWriteConcernOptions($options);
		if ($multiple) {
			$ret =  $this->deleteMany($query, $options);
		}else{
			$ret = $this->deleteOne($query, $options);
		}
		return self::getResult($ret);
	}

	private function deleteMany($query, $options) {
		return $this->_collection->deleteMany($query, $options);
	}

	private function deleteOne($query, $options) {
		return $this->_collection->deleteOne($query, $options);
	}

	/**
	 * @return MongoCursor a cursor for the search results.
	 */
	public function find($query, $options = array()) {
		return new Mongodloid_Cursor('find', $this->_collection, self::fromMongodloid($query), $options);
	}

	/**
	 * Check if a certain entity exists in the collection.
	 * @return boolean true if the query returned results.
	 */
	public function exists($query) {
		if (!$query) {
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
		if (count($args) > 1) { // Assume the array contains 'ops' for backward compatibility
			$args = array($args);
		}
		$pipeline = $args[0] ?? array();
		$options = $args[1] ?? array();
		return new Mongodloid_Cursor('aggregate', $this->_collection, self::fromMongodloid($pipeline), $options);
	}

	public function aggregateWithOptions() {
		$args = func_get_args();
		$pipeline = $args[0] ?? array();
		$options = $args[1] ?? array();
		return new Mongodloid_Cursor('aggregate', $this->_collection, self::fromMongodloid($pipeline), $options);
	}

	public function setTimeout($timeout) {//
		if ($this->_db->compareClientVersion('1.5.3', '<')) {
			@Mongodloid_Cursor::$timeout = (int) $timeout;
		} else {
			// see bugs:
			// https://jira.mongodb.org/browse/PHP-1099
			// https://jira.mongodb.org/browse/PHP-1080
		}
	}

	public function getTimeout() {
		return Mongodloid_Cursor::$timeout;
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
		if (defined('MongoDB\Driver\ReadPreference::' . $readPreference)) {
			$mode = constant('MongoDB\Driver\ReadPreference::' . $readPreference);
		} else if (in_array($readPreference, Mongodloid_Connection::$availableReadPreferences)) {
			$mode = $readPreference;
		} else {
			return false;
		}
		$options = [
			'readPreference' => new \MongoDB\Driver\ReadPreference($mode, $tags),
			'writeConcern' => $this->getWriteConcern(),
		];
		return $this->withOptions($options);
	}

	/**
	 * @return \MongoDB\Collection
	 */
	private function withOptions($options) {
		return $this->_collection->withOptions($options);
	}

	/**
	 * method to load Mongo DB reference object
	 * 
	 * @param MongoDBRef $ref the reference object
	 * 
	 * @return array
	 */
	public function getRef($ref) {//
		if (!MongoDBRef::isRef($ref)) {
			return;
		}
		return new Mongodloid_Entity(MongoDBRef::get($this->_db, $ref));
	}

	/**
	 * method to create Mongo DB reference object
	 * 
	 * @param Mongodloid_Entity $entity Entity to create ref by.
	 * 
	 * @return MongoDBRef
	 */
	public function createRefByEntity($entity) {//
		// TODO: Validate the entity?
		if (is_array($entity)) {
			$refData = $entity;
		} else if ($entity instanceof Mongodloid_Entity) {
			$refData = $entity->getRawData();
		} else {
			return false;
		}
		return $this->createRef($refData);
	}

	/**
	 * method to create Mongo DB reference object
	 * 
	 * @param array $a raw data of object to create reference to itself; later on you can use the return value to store in other collection
	 * 
	 * @return MongoDBRef
	 */
	public function createRef($document_or_id) {//
		if ($document_or_id instanceof Mongodloid_Id) {
			$id = $document_or_id->getMongoID();
		} elseif (is_object($document_or_id)) {
			if (!isset($document_or_id->_id)) {
				return null;
			}

			$id = $document_or_id->_id;
		} elseif (is_array($document_or_id)) {
			if (!isset($document_or_id['_id'])) {
				return null;
			}

			$id = $document_or_id['_id'];
		} else {
			$id = $document_or_id;
		}

		return MongoDBRef::create($this->getName(), $id);
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
	 * @see https://docs.mongodb.com/php-library/current/reference/class/MongoDBCollection/
	 */
	public function findAndModify(array $query, array $update = array(), array $fields = null, array $options = array(), $retEntity = true) {
		$query = self::fromMongodloid($query);
		if (isset($options['remove'])) {
			unset($options['remove']);
			$ret = $this->findOneAndDelete($query, $options);
		} else {
			if (isset($options['new'])) {
				$options['returnDocument'] = \MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER;
				unset($options['new']);
			}
			$options['projection'] = $fields;
			if (!\MongoDB\is_first_key_operator($update)) {
				$ret = $this->findOneAndReplace($query, $update, $options);
			} else {
				$ret = $this->findOneAndUpdate($query, $update, $options);
			}
		}
		$ret = self::getResult($ret);
		if ($retEntity) {
			return new Mongodloid_Entity($ret, $this);
		}
		return $ret;
	}

	private function findOneAndReplace(array $query, array $update = array(), array $options = array()) {
		return $this->_collection->findOneAndReplace($query, $update, $options);
	}

	private function findOneAndUpdate(array $query, array $update = array(), array $options = array()) {
		return $this->_collection->findOneAndUpdate($query, $update, $options);
	}

	private function findOneAndDelete(array $query, array $options = array()) {
		return $this->_collection->findOneAndDelete($query, $options);
	}

	/**
	 * method to bulk insert of multiple documents
	 * 
	 * @param array $a array or object. If an object is used, it may not have protected or private properties
	 * @param array $options options for the inserts.; see php documentation
	 * 
	 * @return mixed If the w parameter is set to acknowledge the write, returns an associative array with the status of the inserts ("ok") and any error that may have occurred ("err"). Otherwise, returns TRUE if the batch insert was successfully sent, FALSE otherwise
	 * @see https://docs.mongodb.com/php-library/current/reference/method/MongoDBCollection-insertMany/#phpmethod.MongoDB\Collection::insertMany
	 */
	public function batchInsert(array $a, array $options = array()) {
//		if ($this->_db->compareServerVersion('2.6', '>=') && $this->_db->compareClientVersion('1.5', '>=')) {
//			$documents = [];
//			foreach ($a as $doc) {
//				if ($doc instanceof Mongodloid_Entity) {
//					$doc = $doc->getRawData();
//				}
//				$documents[] = $doc;
//			}
//		} else {
//			$documents = $a;
//		}
		$this->convertWriteConcernOptions($options);
		return self::getResult($this->insertMany(self::fromMongodloid($a), $options));
	}

	private function insertMany(array $documents, array $options = array()) {
		return $this->_collection->insertMany($documents, $options);
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
		$this->convertWriteConcernOptions($options);
		if ($ins instanceof Mongodloid_Entity) {
			$a = $ins->getRawData();
			$ret = $this->_collection->insertOne(self::fromMongodloid($a), $options);
			$ins = new Mongodloid_Entity($a);
		} else {
			$a = $ins; // pass by reference - _id is not saved on by-ref var
			$ret = $this->_collection->insertOne(self::fromMongodloid($a), $options);
			$ins = $a;
		}
		return self::getResult($ret);
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
			} catch (Exception $e) {
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
	 * @return MongoDB\Collection
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

	public function distinct($key, array $query = array()) {
		return self::getResult($this->_collection->distinct($key, $query));
	}

	public function getWriteConcern($var = null) {
		// backward compatibility with 1.4
		if ($this->_db->compareClientVersion('1.5', '<')) {
			$ret = array(
				'w' => $this->w,
				'wtimeout' => $this->getTimeout(),
			);
		} else {
			$writeConcern = $this->_collection->getWriteConcern();
			if ($writeConcern === null) {
				$writeConcern = new \MongoDB\Driver\WriteConcern($this->w);
			}
			$ret = array(
				'w' => $writeConcern->getW(),
				'wtimeout' => $writeConcern->getWtimeout(),
			);
		}

		if (is_null($var)) {
			return $ret;
		}

		if (isset($ret[$var])) {
			return $ret[$var];
		}
	}

	/**
	 * Converts legacy write concern options to a WriteConcern object
	 *
	 * @param array $options
	 * @return array
	 */
	private function convertWriteConcernOptions(&$options) {
		if ((isset($options['session']) && $options['session']->isInTransaction())) {
			$options['wTimeoutMS'] = $options['wTimeoutMS'] ?? $this->getTimeout();
		} else if (!isset($options['w'])) {
			$options['w'] = $this->w;
		}
		if (isset($options['wtimeout']) && !isset($options['wTimeoutMS'])) {
			$options['wTimeoutMS'] = $options['wtimeout'];
		}

		if (isset($options['w']) || !isset($options['wTimeoutMS'])) {
			$collectionWriteConcern = $this->getWriteConcern();

			$wstring = $options['w'] ?? $collectionWriteConcern['w'];
			$wtimeout = $options['wTimeoutMS'] ?? $collectionWriteConcern['wtimeout'];
			$writeConcern = new \MongoDB\Driver\WriteConcern($wstring, max($wtimeout, 0));
			$options['writeConcern'] = $writeConcern;
		}
		//todo:: check if still relevant
//		if (!isset($options['j']) && $this->_db->compareServerVersion('3.4', '<') && !extension_loaded('mongodb')) {
//			$options['j'] = $this->j;
//		}
		unset($options['w']);
		unset($options['wTimeout']);
		unset($options['wTimeoutMS']);
	}

	public static function getResult($result) {
		$callingMethod = self::getCallingMethodName();
		switch ($callingMethod) {
			case 'update':
				return self::buildUpdateResult($result);
			case 'remove':
				return self::buildRemoveResult($result);
			default:
				return self::toMongodloid($result);
		}
	}

	private static function buildRemoveResult($result) {


		if (!$result->isAcknowledged()) {
			return true;
		}

		return [
			'ok' => 1.0,
			'n' => $result->getDeletedCount(),
			'err' => null,
			'errmsg' => null
		];
	}

	private static function buildUpdateResult($result) {
		

		if (!$result->isAcknowledged()) {
			return true;
		}

		return [
			'ok' => 1.0,
			'nModified' => $result->getModifiedCount(),
			'n' => $result->getMatchedCount(),
			'err' => null,
			'errmsg' => null,
			'updatedExisting' => $result->getUpsertedCount() == 0 && $result->getModifiedCount() > 0,
		];
	}

	/**
	 * Converts a BSON type to the Mongodloid types
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public static function toMongodloid($value) {
		switch (true) {
			case $value instanceof MongoDB\BSON\Type:
				return self::convertBSONObjectToMongodloid($value);
			case is_array($value):
			case is_object($value):
				$result = [];

				foreach ($value as $key => $item) {
					$result[$key] = self::toMongodloid($item);
				}

				return $result;
			default:
				return $value;
		}
	}

	/**
	 * Converter method to convert a BSON object to its Mongodloid type
	 *
	 * @param BSON\Type $value
	 * @return mixed
	 */
	private static function convertBSONObjectToMongodloid(MongoDB\BSON\Type $value) {
		if (!$value) {
			return false;
		}
		switch (true) {
			case $value instanceof MongoDB\BSON\ObjectID:
				return new Mongodloid_Id($value);
			case $value instanceof MongoDB\BSON\Regex:
				return new Mongodloid_Regex($value);
			case $value instanceof MongoDB\BSON\UTCDatetime:
				return new Mongodloid_Date($value);
			case $value instanceof MongoDB\Model\BSONDocument:
			case $value instanceof MongoDB\Model\BSONArray:
				return array_map(
					['self', 'toMongodloid'],
					$value->getArrayCopy()
				);
			default:
				return $value;
		}
	}
	
	/**
     * Converts a Mongodloid type to the new BSON type
     *
     * @param mixed $value
     * @return mixed
     */
    public static function fromMongodloid($value)
    {
        switch (true) {
            case $value instanceof Mongodloid_TypeInterface:
				return $value->toBSONType();
			case $value instanceof Alcaeus\MongoDbAdapter\TypeInterface://still support mongo - after remove all this usages in the code can remove this.
				return $value->toBSONType();
            case $value instanceof MongoDB\BSON\Type:
                return $value;
            case is_array($value):
            case is_object($value):
                $result = [];

                foreach ($value as $key => $item) {
                    $result[$key] = self::fromMongodloid($item);
                }

                return $result;
            default:
                return $value;
        }
    }

	private static function getCallingMethodName() {
		return debug_backtrace()[2]['function'];
	}

}
