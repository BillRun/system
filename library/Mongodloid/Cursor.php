<?php

/**
 * @package         Mongodloid
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */
class Mongodloid_Cursor implements Iterator, Countable {

	private $_cursor;

	public function __construct(MongoCursor $cursor) {
		$this->_cursor = $cursor;
	}

	public function count($foundOnly = true) {
		return $this->_cursor->count($foundOnly);
	}

	public function current() {
		//If before the start of the vector move to the first element.
		if (!$this->_cursor->current() && $this->_cursor->hasNext()) {
			$this->_cursor->next();
		}
		return new Mongodloid_Entity($this->_cursor->current());
	}

	public function key() {
		return $this->_cursor->key();
	}

	public function next() {
		return $this->_cursor->next();
	}

	public function rewind() {
		$this->_cursor->rewind();
		return $this;
	}

	public function valid() {
		return $this->_cursor->valid();
	}

	public function sort(array $fields) {
		$this->_cursor->sort($fields);
		return $this;
	}

	public function limit($limit) {
		$this->_cursor->limit(intval($limit));
		return $this;
	}

	public function skip($limit) {
		$this->_cursor->skip(intval($limit));
		return $this;
	}

	public function hint(array $key_pattern) {
		if (empty($key_pattern)) {
			return;
		}
		$this->_cursor->hint($key_pattern);
		return $this;
	}

	public function explain() {
		return $this->_cursor->explain();
	}

	public function setReadPreference($read_preference, array $tags = array()) {
		$this->_cursor->setReadPreference($read_preference, $tags);
		return $this;
	}

	public function timeout($ms) {
		$this->_cursor->timeout($ms);
		return $this;
	}

	public function immortal($liveForever = true) {
		$this->_cursor->immortal($liveForever);
		return $this;
	}
	
	public function fields(array $fields) {
		$this->_cursor->fields($fields);
		return $this;
	}

}
