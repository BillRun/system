<?php

/**
Copyright (c) 2009, Valentin Golev
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.

    * Redistributions in binary form must reproduce the above copyright notice,
      this list of conditions and the following disclaimer in the documentation
      and/or other materials provided with the distribution.

    * The names of contributors may not be used to endorse or promote products
      derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */
class Mongodloid_Collection {

	private $_collection;
	private $_db;

	const UNIQUE = 1;
	const DROP_DUPLICATES = 2;

	public function __construct(MongoCollection $collection, Mongodloid_DB $db) {
		$this->_collection = $collection;
		$this->_db = $db;
	}

	public function update($query, $values, $options = array()) {
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

	public function save(Mongodloid_Entity $entity, $save = false, $w = 1) {
		$data = $entity->getRawData();

		$result = $this->_collection->save($entity->getRawData(), array('save' => $save, 'w' => $w));
		if (!$result)
			return false;

		$entity->setRawData($data);
		return true;
	}

	public function findOne($id, $want_array = false) {
		$values = $this->_collection->findOne(array('_id' => $id->getMongoId()));
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

	public function remove($query) {
		if ($query instanceOf Mongodloid_Entity)
			$query = $query->getId();

		if ($query instanceOf Mongodloid_Id)
			$query = array('_id' => $query->getMongoId());

		return $this->_collection->remove($query);
	}

	public function find($query) {
		return $this->_collection->find($query);
	}

	public function aggregate() {
		$timeout = $this->getTimeout();
		$this->setTimeout(-1);
		$args = func_get_args();
		$result = call_user_func_array(array($this->_collection, 'aggregate'), $args);
		$this->setTimeout($timeout);
		if (!isset($result['ok']) || !$result['ok']) {
			throw new Mongodloid_Exception('aggregate failed with the following error: ' . $result['code'] . ' - ' . $result['errmsg']);
			return false;
		}
		return $result['result'];
	}

	public function setTimeout($timeout) {
		MongoCursor::$timeout = (int) $timeout;
	}

	public function getTimeout() {
		return MongoCursor::$timeout;
	}

}