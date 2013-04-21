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
class Mongodloid_Cursor implements Iterator {

	private $_cursor;

	public function __construct(MongoCursor $cursor) {
		$this->_cursor = $cursor;
		$timeout = Billrun_Factory::config()->getConfigValue('db.timeout', 3600000); // default 60 minutes
		$this->_cursor->timeout($timeout);
	}

	public function count() {
		return $this->_cursor->count();
	}

	public function current() {
		//If before the start of the vector move to the first element.
		if(!$this->_cursor->current() && $this->_cursor->hasNext()) {
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

}