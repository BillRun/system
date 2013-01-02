<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */
require_once 'Entity.php';
require_once 'Query.php';

class Mongodloid_Collection
{

	private $_collection;
	private $_db;

	const UNIQUE = 1;
	const DROP_DUPLICATES = 2;

	public function __construct(MongoCollection $collection, Mongodloid_DB $db)
	{
		$this->_collection = $collection;
		$this->_db = $db;
	}

	public function update($query, $values)
	{
		return $this->_collection->update($query, $values);
	}

	public function getName()
	{
		return $this->_collection->getName();
	}

	public function dropIndexes()
	{
		return $this->_collection->deleteIndexes();
	}

	public function dropIndex($field)
	{
		return $this->_collection->deleteIndex($field);
	}

	public function ensureUniqueIndex($fields, $dropDups = false)
	{
		return $this->ensureIndex($fields, $dropDups ? self::DROP_DUPLICATES : self::UNIQUE);
	}

	public function ensureIndex($fields, $params = array())
	{
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

	public function getIndexedFields()
	{
		$indexes = $this->getIndexes();

		$fields = array();
		foreach ($indexes as $index)
		{
			$keys = array_keys($index->get('key'));
			foreach ($keys as $key)
				$fields[] = $key;
		}

		return $fields;
	}

	public function getIndexes()
	{
		$indexCollection = $this->_db->getCollection('system.indexes');
		return $indexCollection->query('ns', $this->_db->getName() . '.' . $this->getName());
	}

	public function query()
	{
		$query = new Mongodloid_Query($this);
		if (func_num_args())
		{
			$query = call_user_func_array(array($query, 'query'), func_get_args());
		}
		return $query;
	}

	public function save(Mongodloid_Entity $entity, $save = false, $w =1)
	{
		$entity->updateSaveTime();
		$data = $entity->getRawData();

		$result = $this->_collection->save($entity->getRawData(),array('save'=>$save, 'w' => $w));
		if (!$result)
			return false;

		$entity->setRawData($data);
		return true;
	}

	public function findOne($id, $want_array = false)
	{
		$values = $this->_collection->findOne(array('_id' => $id->getMongoId()));
		if ($want_array)
			return $values;

		return new Mongodloid_Entity($values, $this);
	}

	public function drop()
	{
		return $this->_collection->drop();
	}

	public function count()
	{
		return $this->_collection->count();
	}

	public function clear()
	{
		return $this->remove(array());
	}

	public function remove($query)
	{
		if ($query instanceOf Mongodloid_Entity)
			$query = $query->getId();

		if ($query instanceOf Mongodloid_ID)
			$query = array('_id' => $query->getMongoId());

		return $this->_collection->remove($query);
	}

	public function find($query)
	{
		return $this->_collection->find($query);
	}

}