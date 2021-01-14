<?php

/**
 * @package         Mongodloid
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
class Mongodloid_Entity implements ArrayAccess {

	private $_values;
	
	/**
	 * The entity's collection
	 * @var Mongodloid_Collection
	 */
	private $_collection;

	const POPFIRST = 1;

//	protected $w = 0;
//	protected $j = false;
	private $_atomics = array(
		'inc',
//		'set',
		'push',
		'pushAll',
		'pop',
		'shift',
		'pull',
		'pullAll'
	);
	
 	/**
	 * method to load DB reference object
	 * @param string $key the key of the current object which reference to another object
     * @return array the raw data of the reference object
	 * @deprecated since version 4. Moved this logic to Collection.createRefByEntity
 	 */
	protected function loadRef($key) {
		if (!$this->collection()) {
				return;
		}
		return $this->_collection->getRef($key);
	}

	/**
	 * Create a new instance of the Mongodloid_Entity object.
	 * @param Mongodloid_Collection $values - Values to fill the record with.
	 * @param Mongodloid_Collection $collection - Collection this record was extracted from.
	 * @throws Mongodloid_Exception
	 */
	public function __construct($values = null, $collection = null) {
		if ($values instanceOf Mongodloid_Id) {
			if (!$collection instanceOf Mongodloid_Collection)
				throw new Mongodloid_Exception('You need to specify the collection');

			$values = $collection->findOne($values, true);
		}

		if ($values instanceOf Mongodloid_Collection) {
			$collection = $values;
			$values = null;
		}

		if (!is_array($values))
			$values = array();

		$this->setRawData($values);
		$this->collection($collection);
	}

	/**
	 * Check if two mongodloif entities are identical.
	 * @param Mongodloid_Entity $obj - Entity to compare.
	 * @return boolean - True if identical.
	 */
	public function same(Mongodloid_Entity $obj) {
		return $this->getId() && ((string) $this->getId() == (string) $obj->getId());
	}

	/**
	 * Override the equals function.
	 * @param Mongodloid_Entity $obj
	 * @return type
	 */
	public function equals(Mongodloid_Entity $obj) {
		$data1 = $this->getRawData();
		$data2 = $obj->getRawData();
		unset($data1['_id'], $data2['_id']);

		return $data1 == $data2;
	}

	public function inArray($key, $value) {
		if ($value instanceOf Mongodloid_Entity) {
			// TODO: Add DBRef checking
			return $this->inArray($key, $value->getId()) || $this->inArray($key, (string) $value->getId());
		}

		return in_array($value, $this->get($key));
	}

	public function __call($name, $params) {
		if (in_array($name, $this->_atomics)) {
			$value = $this->get($params[0]);
			switch ($name) {
				case 'inc':
					if ($params[1] === null)
						$params[1] = 1;
					$value += $params[1];
					break;
				case 'push':
					if (!is_array($value))
						$value = array();
					$value[] = $params[1];
					break;
				case 'pushAll':
					if (!is_array($value))
						$value = array();
					$value += $params[1];
					break;
				case 'shift':
					$name = 'pop';
					$params[1] = self::POPFIRST;
				case 'pop':
					$params[1] = ($params[1] == self::POPFIRST) ? -1 : 1;
					if ($params[1] == -1) {
						array_shift($value);
					} else {
						array_pop($value);
					}
					break;
				case 'pull':
					/*
					  if (($key = array_search($params[1], $value)) !== FALSE) {
					  unset($value[$key]);
					  }
					 */
					$_value = array();
					foreach ($value as $val) {
						if ($val !== $params[1]) {
							$_value[] = $val;
						}
					} // save array indexes - save the world!
					$value = $_value;
					break;
				case 'pullAll':
					$_value = array();
					foreach ($value as $val) {
						if (!in_array($val, $params[1])) {
							$_value[] = $val;
						}
					}
					$value = $_value;
					break;
			}

			$value = $this->set($params[0], $value);

			if ($this->getId()) {
				$this->update(array(
					'$' . $name => array(
						$params[0] => $params[1]
					)
				));
			}

			return $this;
		}

		throw new Mongodloid_Exception(__CLASS__ . '::' . $name . ' does not exist and hasn\'t been trapped in __call()');
	}

	/**
	 * Set the values of the entity.
	 * @param type $key - Key for the value to be set.
	 * @param type $value - Value to be set.
	 * @return \Mongodloid_Entity
	 */
	public function set($key, $value) {
		$key = preg_replace('@\\[([^\\]]+)\\]@', '.$1', $key);
		$real_key = $key;
		$result = &$this->_values;

		$keys = explode('.', $key);
		foreach ($keys as $key) {
			$result = &$result[$key];
		}

		$result = $value;

		return $this;
	}

	public function get() {
		if (func_num_args() == 0) {
			return $this->_values;
		}
		$key = func_get_arg(0);

		if ($key === '_id') {
			return $this->getId();
		}

		$getRef = func_num_args() > 1 ? func_get_arg(1) : false;

		$key = preg_replace('@\\[([^\\]]+)\\]@', '.$1', $key);
		$result = $this->_values;

		// if this is chained key, let's pull it
		if (strpos($key, '.') !== FALSE) {
			do {
				list($current, $key) = explode('.', $key, 2);
				if (isset($result[$current])) {
					$result = $result[$current];
				} else {
					// if key is not in the values, let's return null -> not found key
					return null;
				}
			} while (strpos($key, '.') !== FALSE);
		}

		if (!isset($result[$key])) {
			return null;
		}

		if (!$getRef) {
			// TODO: This logic needs to be moved to the collection object.
			//lazy load MongoId Ref objects or Mongodloid_Ref
			//http://docs.mongodb.org/manual/reference/database-references/
			if ($result[$key] instanceof Mongodloid_Id && $this->collection()) {
				$result[$key] = $this->collection()->findOne($result[$key]['$id']);
			} else if (Mongodloid_Ref::isRef($result[$key])) {
				$collection = $this->collection();
				// TODO: Report error if collection is null?
				if($collection) {
					$result[$key] = $collection->getRef($result[$key]);
				}
			}
		}

		return $result[$key];
	}

	/**
	 * method to create Mongodloid_Ref from the current entity
	 * 
	 * @param Mongodloid_Collection $refCollection the collection to reference to
	 * 
	 * @return mixed Mongodloid_Ref if succeed, else false
	 * @todo check if the current id exists in the collection
	 * @todo Change all calls to this function to calls to the collection createRefByEntity function.
	 */
	public function createRef($refCollection = null) {
		if (!is_null($refCollection)) {
			$this->collection($refCollection);
		} else if (!$this->collection()) {
			return;
		}
		return $this->collection()->createRef($this->getRawData());
	}

	/**
	 * Get the mongo ID of the record.
	 * @return \Mongodloid_Id|boolean - false if no ID is set.
	 */
	public function getId() {
		if (!isset($this->_values['_id']) || !$this->_values['_id']) {
			return false;
		}
		
		return new Mongodloid_Id($this->_values['_id']);
	}

	/**
	 * Get the raw data of the entity.
	 * @return type - Array of data.
	 */
	public function getRawData() {
		return $this->_values;
	}

	/**
	 * 
	 * @param array $data
	 * @param boolean $safe
	 * @throws Mongodloid_Exception
	 * @todo consider defaulting $safe to false because most of the time this is the behavior we want
	 */
	public function setRawData($data, $safe = false) {
		if (!is_array($data))
			throw new Mongodloid_Exception('Data must be an array!');

		// prevent from making a link
		if ($safe) {
			$this->_values = unserialize(serialize($data));
		} else {
			$this->_values = $data;
		}
	}
	
	/**
	 * Save this entity into it's collection.
	 * @param type $w
	 * @return type
	 * @deprecated since version 4
	 */
	public function save($w = null) {
		return $this->collection()->save($this, $w);
	}

	/**
	 * Sets or gets the collection.
	 * @param Mongodloid_Collection $collection - If null, returns the current 
	 *	collection, if not, sets the value as the collection and returns the new value.
	 * @return Mongodloid_Collection
	 */
	public function collection($collection = null) {
		if ($collection instanceOf Mongodloid_Collection)
			$this->_collection = $collection;

		return $this->_collection;
	}

	/**
	 * Remove the entity from its collection.
	 * @deprecated since version 4
	 */
	public function remove() {
		if (!$this->collection())
			throw new Mongodloid_Exception('You need to specify the collection');

		if (!$this->getId())
			throw new Mongodloid_Exception('Object wasn\'t saved!');

		return $this->collection()->remove($this);
	}

	/**
	 * Method to create auto increment of document
	 * To use this method require counters collection, created by the next command:
	 * 
	 * @param string $field the field to set the auto increment
	 * @param int $min_id the default value to use for the first value
	 * @return mixed the auto increment value or void on error
	 * @deprecated since version 4
	 */
	// This is called through the collection.
	public function createAutoInc($field, $min_id = 1) {
		// check if already set auto increment for the field
		$value = $this->get($field);
		if ($value) {
			return $value;
		}

		// check if collection exists for the entity
		if (!$this->collection()) {
			// TODO: Report error?
			return;
		}

		// check if id exists (cannot create auto increment without id)
		$id = $this->getId();
		if (!$id) {
			return;
		}

		$inc = $this->collection()->createAutoInc($id->getMongoID(), $min_id);
		$this->set($field, $inc);
		return $inc;
	}

	//=============== ArrayAccess Implementation =============
	public function offsetExists($offset) {
		$result = $this->_values;

		// if this is chained key, let's pull it
		if (strpos($offset, '.') !== FALSE) {
			do {
				list($current, $offset) = explode('.', $offset, 2);
				if (isset($result[$current])) {
					$result = $result[$current];
				} else {
					// if key is not in the values, let's return null -> not found key
					return null;
				}
			} while (strpos($offset, '.') !== FALSE);
		}
		return isset($result[$offset]);
	}

	public function offsetGet($offset) {
		return $this->get($offset);
	}

	public function offsetSet($offset, $value) {
		return $this->set($offset, $value);
	}

	public function offsetUnset($offset) {
		unset($this->_values[$offset]);
	}

	public function isEmpty() {
		return empty($this->_values);
	}

}
