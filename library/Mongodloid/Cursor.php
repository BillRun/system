<?php

/**
 * @package         Mongodloid
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Cursor class layer
 * Some methods exists only with MongoCursor and not with MongoCommandCursor (aggregator cursor)
 *	both are expected in the constructor
 */
class Mongodloid_Cursor implements Iterator, Countable {

	protected $_cursor;
	protected $getRaw = FALSE;

	/**
     * @var int
     */
    public static $timeout = 30000;
	
	/**
	 * Parameter to ensure valid construction.
	 * @var boolean - True if cursor is valid.
	 */
	protected $_isValid = false;
	
	/**
	 * Create a new instance of the cursor object.
	 * @param MongoDB\Driver\Cursor $cursor - Mongo cursor pointing to a collection.
	 */
	public function __construct($cursor) {
		// Check that the cursor is a mongocursor
		if (!$this->validateInputCursor($cursor)) {
			// TODO: Report error?
			return;
		}
		$this->_cursor = $cursor;
		
		if ($this->_cursor instanceof Traversable) {
			$this->_iterator = new IteratorIterator($cursor);
			$this->rewind();
			$this->valid();
		}
		
		$this->_isValid = true;
	}

	/**
	 * Check if input cursor is of mongo cursor type.
	 * @param MongoCursor $cursor
	 * @return type
	 */
	protected function validateInputCursor($cursor) {
		return ($cursor) && ($cursor instanceof MongoDB\Driver\Cursor || (is_object($cursor) && get_class($cursor) == 'Traversable'));
	}
	
	/**
	 * Checks if the cursor is valid.
	 * @return boolean - true if the cursor is valid.
	 * @todo Actually use this function in billrun code.
	 */
	public function isValid() {
		return $this->_isValid;
	}
	
	public function count($foundOnly = true) {//
		
		return $foundOnly ? iterator_count($this->_iterator) :
			iterator_count($this->_iterator);//todo:: need to support $foundOnly
	}

	/**
	 * 
	 * @return \Mongodloid_Entity
	 */
	public function current() {
		//If before the start of the vector move to the first element.
		// 
		if (method_exists($this->_cursor, 'hasNext') && !$this->_iterator->current() && $this->_cursor->hasNext()) {
			$this->next();
		}
		
		return $this->getRaw ? Mongodloid_Collection::getResult($this->_iterator->current()) :  new Mongodloid_Entity(Mongodloid_Collection::getResult($this->_iterator->current()), null, false);
	}

	public function key() {
		return $this->_iterator->key();
	}

	public function next() {
		return $this->_iterator->next();
	}

	public function rewind() {
		$this->_iterator->rewind();
		return $this;
	}

	public function valid() {
		return $this->_iterator->valid();
	}

	public function sort(array $fields) {
		if (method_exists($this->_cursor, 'sort')) {
			$this->_cursor->sort($fields);
		}
		return $this;
	}

	public function limit($limit) {//
		if (method_exists($this->_cursor, 'limit')) {
			$this->_cursor->limit(intval($limit));
		}
		return $this;
	}

	public function skip($limit) {//
		if (method_exists($this->_cursor, 'skip')) {
			$this->_cursor->skip(intval($limit));
		}
		return $this;
	}

	public function hint(array $key_pattern) {
		if (method_exists($this->_cursor, 'hint')) {
			if (empty($key_pattern)) {
				return;
			}
			$this->_cursor->hint($key_pattern);
		}
		return $this;
	}

	public function explain() {
		if (method_exists($this->_cursor, 'explain')) {
			return $this->_cursor->explain();
		}
		return false;
	}

	/**
	 * method to set read preference of cursor connection
	 * 
	 * @param string $readPreference The read preference mode: RP_PRIMARY, RP_PRIMARY_PREFERRED, RP_SECONDARY, RP_SECONDARY_PREFERRED or RP_NEAREST
	 * @param array $tags An array of zero or more tag sets, where each tag set is itself an array of criteria used to match tags on replica set members
	 * 
	 * @return Mongodloid_Cursor self object
	 */
	public function setReadPreference($readPreference, array $tags = array()) {
		if (method_exists($this->_cursor, 'setReadPreference')) {
			if (defined('MongoDB\Driver\ReadPreference::' . $readPreference)) {
				$this->_cursor->setReadPreference(constant('MongoDB\Driver\ReadPreference::' . $readPreference), $tags);
			} else if (in_array($readPreference, Mongodloid_Connection::$availableReadPreferences)) {
				$this->_cursor->setReadPreference($readPreference, $tags);
			}
		}

		return $this;
	}

	/**
	 * method to get read preference of cursor connection
	 * 
	 * @param boolean $includeTage if to include tags in the return value, else return only the read preference
	 * 
	 * @return mixed array in case of include tage else string (the string would be the rp constant)
	 */
	public function getReadPreference($includeTage = false) {
		if (!method_exists($this->_cursor, 'getReadPreference')) {
			return false;
		}
		$ret = $this->_cursor->getReadPreference();
		if ($includeTage) {
			return $ret;
		}
		
		switch ($ret['type']) {
			case MongoDB\Driver\ReadPreference::RP_PRIMARY:
				return 'RP_PRIMARY';
			case MongoDB\Driver\ReadPreference::RP_PRIMARY_PREFERRED:
				return 'RP_PRIMARY_PREFERRED';
			case MongoDB\Driver\ReadPreference::RP_SECONDARY:
				return 'RP_SECONDARY';
			case MongoDB\Driver\ReadPreference::RP_SECONDARY_PREFERRED:
				return 'RP_SECONDARY_PREFERRED';
			case MongoDB\Driver\ReadPreference::RP_NEAREST:
				return 'RP_NEAREST';
			default:
				return MongoDB\Driver\ReadPreference::RP_PRIMARY_PREFERRED;
		}

	}

	public function timeout($ms) {
		if (method_exists($this->_cursor, 'maxTimeMS')) {
			$this->_cursor->maxTimeMS($ms);
		} else if (method_exists($this->_cursor, 'timeout')) {
			$this->_cursor->timeout($ms);
		}
		return $this;
	}

	public function immortal($liveForever = true) {
		if (method_exists($this->_cursor, 'immortal')) {
			$this->_cursor->immortal($liveForever);
		}
		return $this;
	}
	
	public function fields(array $fields) {
		if (method_exists($this->_cursor, 'fields')) {
			$this->_cursor->fields($fields);
		}
		return $this;
	}
	
	public function setRawReturn($enabled) {
		$this->getRaw = $enabled;
		
		return $this;
	} 

}
