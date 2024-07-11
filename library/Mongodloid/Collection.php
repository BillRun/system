<?php

/**
 * @package         Mongodloid
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
class Mongodloid_Collection {

	/**
	 * the mongodb library collection object
	 * 
	 * @var MongoDB\Collection
	 */
	private $_collection;
	private $_db;

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
	 * @param array $query - Query to filter records to update.
	 * @param array $values - Query for updating new values.
	 * @param array $options - Mongo options.
	 * @return mongodloid update result.
	 */
	public function update($query, $values, $options = array()) {

		$multiple = isset($options['multiple']) ? $options['multiple'] : false;
		$isReplace = !\MongoDB\is_first_key_operator($values);

		if ($isReplace && $multiple) {
			throw new Exception('multi update only works with $ operators', 9);
		}
		unset($options['multiple']);
		$this->convertWriteConcernOptions($options);
		$query = Mongodloid_TypeConverter::fromMongodloid($query);
		$values = Mongodloid_TypeConverter::fromMongodloid($values);
		if ($isReplace) {
			$upsert = false;
			if(isset($options['upsert'])){
				unset($options['upsert']);
				$upsert = true;
			}
			$res = Mongodloid_Result::getResult($this->replaceOne($query, $values, $options));
			if($upsert && $res['n'] === 0){
				$res = $this->insert($values, $options);
			}
		} else if ($multiple) {
			$res =  Mongodloid_Result::getResult($this->updateMany($query, $values, $options));
		} else {
			$res = Mongodloid_Result::getResult($this->updateOne($query, $values, $options));
		}
		return $res;
	}

	/**
	 * Update all documents that match the query.
	 * @param array $query - Query to filter records to update
	 * @param array $values - Query for updating new values.
	 * @param array $options - Mongo options.
	 * @return mongodb updateMany result
	 * @see https://docs.mongodb.com/php-library/current/reference/method/MongoDBCollection-updateMany/#phpmethod.MongoDB\Collection::updateMany
	 */
	private function updateMany($query, $values, $options = array()) {
		return $this->_collection->updateMany($query, $values, $options);
	}

	/**
	 * Update at most one document that matches the query. 
	 * If multiple documents match the query,
	 * only the first matching document will be updated.
	 * @param array $query - Query to filter record to update
	 * @param array $values - Query for updating new values.
	 * @param array $options - Mongo options.
	 * @return mongodb updateOne result
	 * @see https://docs.mongodb.com/php-library/current/reference/method/MongoDBCollection-updateOne/#phpmethod.MongoDB\Collection::updateOne
	 */
	private function updateOne($query, $values, $options = array()) {
		return $this->_collection->updateOne($query, $values, $options);
	}

	/**
	 * Replace at most one document that matches the query. 
	 * If multiple documents match the query, 
	 * only the first matching document will be replaced.
	 * @param array $query - Query to filter record to replace
	 * @param array $values - new values.
	 * @param array $options - Mongo options.
	 * @return mongodb replaceOne result
	 * @see https://docs.mongodb.com/php-library/current/reference/method/MongoDBCollection-replaceOne/#phpmethod.MongoDB\Collection::replaceOne
	 */
	private function replaceOne($query, $values, $options = array()) {
		return $this->_collection->replaceOne($query, $values, $options);
	}

	/**
	 * Set the fields into the entity.
	 * @param Mongodloid_Entity - Entity to set values into.
	 * @param array $fields - Fields to set.
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
	 * @return mongodloid updateEntity result.
	 */
	public function updateEntity($entity, $fields = array()) {
		if (empty($fields)) {
			$fields = $entity->getRawData();
			unset($fields['_id']);
		}

		$data = array(
			'_id' => $entity->getId()
		);

		// This function changes fields, should I clone fields before sending?
		$this->setEntityFields($entity, $fields);

		return Mongodloid_Result::getResult($this->updateOne($data, array('$set' => $fields)));
	}

	/**
	 * Returns the name of this collection.
	 * @return mongodloid getName result
	 */
	public function getName() {
		return Mongodloid_Result::getResult($this->_collection->getCollectionName());
	}

	/**
	 * Drop all indexes in the collection,
	 * except for the required index on the _id field.
	 * @return mongodloid dropIndexes result
	 */
	public function dropIndexes() {
		return Mongodloid_Result::getResult($this->_collection->dropIndexes());
	}

	/**
	 * Drop an index from the collection.
	 * @param mixed $field - The name or model object of the index to drop.
	 * @return mongodloid dropIndexe result
	 */
	public function dropIndex($field) {
		return Mongodloid_Result::getResult($this->_collection->dropIndex($field));
	}

	/**
	 * Create an unique index for the collection
	 * @param array $fields
	 * @return mongodloid createIndex result
	 * @see https://docs.mongodb.com/php-library/current/reference/method/MongoDBCollection-createIndex/#phpmethod.MongoDB\Collection::createIndex
	 */
	public function ensureUniqueIndex($fields) {
		$params['unique'] = true;
		return $this->ensureIndex($fields, $params);
	}

	/**
	 * Create an index for the collection
	 * @param array $fields - Specifies the field or fields to index and the index order.
	 * @param array $params
	 * @return mongodloid createIndex result
	 * @see https://docs.mongodb.com/php-library/current/reference/method/MongoDBCollection-createIndex/#phpmethod.MongoDB\Collection::createIndex
	 * @deprecated since version 5.15 [please use createIndex]
	 */
	public function ensureIndex($fields, $params = array()) {
		return $this->createIndex($fields, $params);
	}
	
	/**
	 * Create an index for the collection
	 * @param array $fields - Specifies the field or fields to index and the index order.
	 * @param array $params
	 * @return mongodloid createIndex result
	 * @see https://docs.mongodb.com/php-library/current/reference/method/MongoDBCollection-createIndex/#phpmethod.MongoDB\Collection::createIndex
	 */
	public function createIndex($fields, $params = array()) {
		if (!is_array($fields)) {
			$fields = array($fields => 1);
		}
		return Mongodloid_Result::getResult($this->_collection->createIndex($fields, $params));
	}
	
	/**
	 * get all indexes fields
	 * @return array - Indexed Fields
	 */
	public function getIndexedFields() {
		$indexes = $this->getIndexes();

		$fields = array();
		foreach ($indexes as $index) {
			$keys = array_keys($index['key']);
			foreach ($keys as $key)
				$fields[] = $key;
		}

		return $fields;
	}

	/**
	 * Returns information for all indexes for this collection.
	 * @return mongodloid getIndexes result.
	 * @see https://docs.mongodb.com/php-library/current/reference/method/MongoDBCollection-listIndexes/#phpmethod.MongoDB\Collection::listIndexes
	 */
	public function getIndexes() {
		return Mongodloid_Result::getResult($this->_collection->listIndexes());
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

	/**
	 * Saves a document to this collection
	 * @param Mongodloid_Entity $entity
	 * @param type $w
	 * @return mongodloid save result.
	 */
	public function save(Mongodloid_Entity $entity, $w = null) {
		$options = array(
			'w' => $w
		);
		$this->convertWriteConcernOptions($options);
		$data = $entity->getRawData();
		$id = $entity->getId();
		if($id){
			$options['upsert'] = true;
			$result = Mongodloid_Result::getResult($this->update(array('_id' => Mongodloid_TypeConverter::fromMongodloid($id)), $data, $options));
			$entity->setRawData($data);
		}else{
			$result = Mongodloid_Result::getResult($this->insert($data, $options));
			$entity->setRawData($data);
		}
		
		return $result;
	}

	/**
	 * Finds a single document that '_id' matching to the given id.
	 * @param Monogodloid_Id $id  - ID of mongo record to be find.
	 * @param boolean $want_array
	 * @return array\Mongodloid_Entity - dependence on $want_array
	 */
	public function findOne($id, $want_array = false) {
		$values = Mongodloid_Result::getResult($this->_collection->findOne(array('_id' => Mongodloid_TypeConverter::fromMongodloid($id))));

		if ($want_array) {
			return $values;
		}
		return new Mongodloid_Entity($values, $this);
	}

	/**
	 * Drop the collection.
	 * @return mongodloid drop result.
	 * @see https://docs.mongodb.com/php-library/current/reference/method/MongoDBCollection-drop/#phpmethod.MongoDB\Collection::drop
	 */
	public function drop() {
		return Mongodloid_Result::getResult($this->_collection->drop());
	}

	/**
	 * Count the number of documents in this collection
	 * @return mongodloid count result.
	 */
	public function count() {
		return Mongodloid_Result::getResult($this->_collection->count());
	}

	/**
	 * Count the number of documents in this collection
	 * @return mongodloid count result.
	 */
	public function countDocuments() {
		return $this->count();
	}

	/**
	 * Count the number of documents in this collection
	 * @return mongodloid count result.
	 */
	public function estimatedDocumentCount() {
		return Mongodloid_Result::getResult($this->_collection->estimatedDocumentCount());
	}

	public function clear() {//TODO:: check this - I dont think this works also before changes
		return $this->remove(array());
	}

	/**
	 * Remove an entity from the collection.
	 * @param Mongoldoid_Entity $entity - Entity to remove from the collection.
	 * @param array $options - Options to send to the mongodb
	 * @return mongodloid remove result
	 */
	public function removeEntity($entity, $options = array('w' => 1)) {
		$query = $entity->getId();
		return $this->remove($query, $options);
	}

	/**
	 * Remove an entity from the collection.
	 * @param Mongoldoid_Id $id - ID of mongo record to be removed.
	 * @param array $options - Options to send to the mongodb
	 * @return mongodloid remove result
	 */
	public function removeId($id, $options = array('w' => 1)) {
		$query = array('_id' => $id);
		return $this->remove($query, $options);
	}

	/**
	 * Remove data from the collection.
	 * @param Query $query - Query object or Mongoldoid_Entity to use to remove data.
	 * @param array $options - Options to send to the mongo
	 * @return mongodloid remove result.
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
			$query = array('_id' => $query);

		$this->convertWriteConcernOptions($options);
		$query = Mongodloid_TypeConverter::fromMongodloid($query);
		if ($multiple) {
			$ret =  $this->deleteMany($query, $options);
		}else{
			$ret = $this->deleteOne($query, $options);
		}
		return Mongodloid_Result::getResult($ret);
	}

	/**
	 * Deletes all documents that match the filter criteria.
	 * @param array|object $query - The filter criteria that specifies the documents to delete
	 * @param array $options - An array specifying the desired options.
	 * @return MongoDB\DeleteResult
	 * @see https://docs.mongodb.com/php-library/current/reference/method/MongoDBCollection-deleteMany/#phpmethod.MongoDB\Collection::deleteMany
	 */
	private function deleteMany($query, $options) {
		return $this->_collection->deleteMany($query, $options);
	}

	/**
	 * Deletes at most one document that matches the filter criteria.
	 * If multiple documents match the filter criteria,
	 * only the first matching document will be deleted.
	 * @param array|object $query - The filter criteria that specifies the documents to delete
	 * @param type $options - An array specifying the desired options
	 * @return MongoDB\DeleteResult
	 * @see https://docs.mongodb.com/php-library/current/reference/method/MongoDBCollection-deleteOne/#phpmethod.MongoDB\Collection::deleteOne
	 */
	private function deleteOne($query, $options) {
		return $this->_collection->deleteOne($query, $options);
	}

	/**
	 * Finds documents matching the query.
	 * @param array|object $query - The filter criteria that specifies the documents to query
	 * @param array $fields - Fields of the results to return.
	 * @return Mongodloid_Cursor - a cursor for the search results.
	 * @see https://docs.mongodb.com/php-library/current/reference/method/MongoDBCollection-find/#phpmethod.MongoDB\Collection::find
	 */
	public function find($query, $fields = array()) {
		$options['projection'] = $fields;
		$cursor = new Mongodloid_Cursor('find', $this->_collection, Mongodloid_TypeConverter::fromMongodloid($query), $options);
		$cursor->setRawReturn(true);
		return $cursor;
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
		return new Mongodloid_Cursor('aggregate', $this->_collection, Mongodloid_TypeConverter::fromMongodloid($pipeline));
	}

	public function aggregateWithOptions() {
		$args = func_get_args();
		$pipeline = $args[0] ?? array();
		$options = $args[1] ?? array();
		return new Mongodloid_Cursor('aggregate', $this->_collection, Mongodloid_TypeConverter::fromMongodloid($pipeline), $options);
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
			'writeConcern' => $this->_collection->getWriteConcern(),
		];
		$this->withOptions($options);
		return $this;
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
	 * @param Mongodloid_Ref $ref the reference object
	 * 
	 * @return array
	 */
	public function getRef($ref) {//
		if (!Mongodloid_Ref::isRef($ref)) {
			return;
		}
		return Mongodloid_Ref::get($this->_db, $ref);
	}

	/**
	 * method to create Mongo DB reference object
	 * 
	 * @param Mongodloid_Entity $entity Entity to create ref by.
	 * 
	 * @return Mongodloid_Ref
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
	 * @return Mongodloid_Ref
	 */
	public function createRef($document_or_id) {//
		if ($document_or_id instanceof Mongodloid_Id) {
			$id = $document_or_id;
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

		return Mongodloid_Ref::create($this->getName(), Mongodloid_TypeConverter::fromMongodloid($id));
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
		$query = Mongodloid_TypeConverter::fromMongodloid($query);
		
		if (isset($options['remove'])) {
			unset($options['remove']);
			$ret = $this->findOneAndDelete($query, $options);
		} else {
			if (isset($options['new'])) {
				$options['returnDocument'] = \MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER;
				unset($options['new']);
			}
			$options['projection'] = $fields;
			$update = Mongodloid_TypeConverter::fromMongodloid($update);
			if (!\MongoDB\is_first_key_operator($update)) {
				$ret = $this->findOneAndReplace($query, $update, $options);
			} else {
				$ret = $this->findOneAndUpdate($query, $update, $options);
			}
		}
		$ret = Mongodloid_Result::getResult($ret);
		if ($retEntity) {
			return new Mongodloid_Entity($ret, $this);
		}
		return $ret;
	}

	/**
	 * Finds a single document matching the query and replaces it.
	 * @param array|object $query - The filter criteria that specifies the documents to replace.
	 * @param array|object $update - The replacement document.
	 * @param array $options - An array specifying the desired options.
	 * @return object|null
	 * @see https://docs.mongodb.com/php-library/current/reference/method/MongoDBCollection-findOneAndReplace/#phpmethod.MongoDB\Collection::findOneAndReplace
	 */
	private function findOneAndReplace($query, $update = array(), array $options = array()) {
		return $this->_collection->findOneAndReplace($query, $update, $options);
	}

	/**
	 * Finds a single document matching the query and updates it.
	 * @param array|object $query - The filter criteria that specifies the documents to update.
	 * @param array|object $update
	 * @param array $options - An array specifying the desired options.
	 * @return object|null
	 * @see https://docs.mongodb.com/php-library/current/reference/method/MongoDBCollection-findOneAndUpdate/#phpmethod.MongoDB\Collection::findOneAndUpdate
	 */
	private function findOneAndUpdate($query, $update = array(), array $options = array()) {
		return $this->_collection->findOneAndUpdate($query, $update, $options);
	}

	/**
	 * Finds a single document matching the query and deletes it.
	 * @param array|object $query - The filter criteria that specifies the documents to delete.
	 * @param array $options - An array specifying the desired options.
	 * @return object|null
	 * @see https://docs.mongodb.com/php-library/current/reference/method/MongoDBCollection-findOneAndDelete/#phpmethod.MongoDB\Collection::findOneAndDelete
	 */
	private function findOneAndDelete($query, array $options = array()) {
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
		$documents = [];
		foreach ($a as $doc) {
			if ($doc instanceof Mongodloid_Entity) {
				$doc = $doc->getRawData();
			}
			$documents[] = $doc;
		}
		$this->convertWriteConcernOptions($options);
		return Mongodloid_Result::getResult($this->insertMany(Mongodloid_TypeConverter::fromMongodloid($documents), $options));
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
			$ret = $this->_collection->insertOne(Mongodloid_TypeConverter::fromMongodloid($a), $options);
            $a['_id'] = new Mongodloid_Id($ret->getInsertedId());
			$ins = new Mongodloid_Entity($a);
		} else {
			$a = $ins; // pass by reference - _id is not saved on by-ref var
			$ret = $this->_collection->insertOne(Mongodloid_TypeConverter::fromMongodloid($a), $options);
			$ins = $a;
            $ins['_id'] = new Mongodloid_Id($ret->getInsertedId());
		}
		return Mongodloid_Result::getResult($ret);
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

		$inc = $this->createAutoInc(Mongodloid_TypeConverter::fromMongodloid($id), $min_id);

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
		$query = Mongodloid_TypeConverter::fromMongodloid($query);
		return Mongodloid_Result::getResult($this->_collection->distinct($key, $query));
	}

	public function getWriteConcern($var = null) {
		// backward compatibility with 1.4
		$writeConcern = $this->_collection->getWriteConcern();
		if ($writeConcern === null) {
			$writeConcern = new \MongoDB\Driver\WriteConcern($this->w);
		}
		$ret = array(
			'w' => $writeConcern->getW(),
			'wtimeout' => $writeConcern->getWtimeout(),
		);

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

}
