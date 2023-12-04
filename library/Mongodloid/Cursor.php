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
	protected $_options;
	protected $_query;
	protected $_iterator;

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
	public function __construct($command, $collection, $query, $options = array()) {
		$cursor = $collection->$command($query, $options);
		// Check that the cursor is a MongoDB\Driver\Cursor
		if (!$this->validateInputCursor($cursor)) {
			// TODO: Report error?
			return;
		}
		$this->_collection = $collection;
		$this->_command = $command;
		$this->_cursor = $cursor;
		$this->_options = $options;
		$this->_query = $query;
		
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
	
	public function count($foundOnly = true) {
		$options =  $this->_options;
		if(!$foundOnly){
			unset($options['skip']);
			unset($options['limit']);
		}
		return $this->_collection->count($this->_query, $options);
	}

	/**
	 * 
	 * @return \Mongodloid_Entity
	 */
	public function current() {
		//If before the start of the vector move to the first element.
		// 
		if ($this->_iterator === null) {
          $this->doQuery();
        }
		
		return $this->getRaw ? Mongodloid_Result::getResult($this->_iterator->current()) :  new Mongodloid_Entity(Mongodloid_Result::getResult($this->_iterator->current()), null, false);
	}

	public function key() {
		if ($this->_iterator === null) {
            return;
        }
		$current = $this->current();

        if ($current instanceof Mongodloid_Entity && $current->getId()){
            return $current->getId()->__toString();
        }
		return $this->_iterator->key();
	}

	public function next() {
		if ($this->_iterator === null) {
          $this->doQuery();
		  return $this->current();
        }
		$this->_iterator->next();
		return $this->current();
	}

	public function rewind() {
		if (!is_null($this->_iterator)) {
			$lastCalledStack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
			error_log('double running on cursor in ' . $lastCalledStack[0]['file'] . ' line ' . $lastCalledStack[0]['line']);
		}

		$this->doQuery();
		return $this;
	}
	
	public function reset() {
		$this->_iterator = null;
	}

	public function valid() {
		if ($this->_iterator === null) {
            false;
        }
		return $this->_iterator->valid();
	}

	/**
     * Sorts the results by given fields
     * @param array $fields An array of fields by which to sort. Each element in the array has as key the field name, and as value either 1 for ascending sort, or -1 for descending sort
     * @throws Exception
     * @return MongoDB\Driver\Cursor Returns the same cursor that this method was called on
     */
	public function sort(array $fields) {
		$this->errorIfOpened();
		$this->_options['sort'] = $fields;
		return $this;
	}

	/**
     * Limits the number of results returned
     * @param int $limit The number of results to return.
     * @throws Exception
     * @return MongoDB\Driver\Cursor Returns this cursor
     */
	public function limit($limit) {
		$this->errorIfOpened();
		$this->_options['limit'] = intval($limit);
		return $this;
	}

	/**
     * Skips a number of results
     * @param int $limit The number of results to skip.
     * @throws Exception
     * @return MongoDB\Driver\Cursor Returns this cursor
     */
	public function skip($limit) {
		$this->errorIfOpened();
		$this->_options['skip'] = intval($limit);
		return $this;
	}

	/**
     * Gives the database a hint about the query
     * @param array|string $key_pattern Indexes to use for the query.
     * @throws Exception
     * @return MongoDB\Driver\Cursor Returns this cursor
     */
	public function hint(array $key_pattern) {
		$this->errorIfOpened();
		if (empty($key_pattern)) {
			return;
		}
		$this->_options['hint'] = $key_pattern;
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
		$this->errorIfOpened();
		
		if (defined('MongoDB\Driver\ReadPreference::' . $readPreference)) {
			$mode = constant('MongoDB\Driver\ReadPreference::' . $readPreference);
		} else if (in_array($readPreference, Mongodloid_Connection::$availableReadPreferences)) {
			$mode = $readPreference;
		}else{
			return $this;
		}
		$this->_options['readPreference'] = new \MongoDB\Driver\ReadPreference($mode, $tags);
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
		$this->errorIfOpened();
		$this->_options['maxTimeMS'] = $ms;
		return $this;
	}

	public function immortal($liveForever = true) {
		$this->errorIfOpened();
		$this->_options['noCursorTimeout'] = $liveForever;
		return $this;
	}
	
	/**
     * Sets the fields for a query
     * @param array $fields Fields to return (or not return).
     * @throws Exception
     * @return MongoDB\Driver\Cursor Returns this cursor
     */
	public function fields(array $fields) {
		$this->errorIfOpened();
		// from mongodb 4.4 the values should be casted to int
		$project = array_map(function($val) {settype($val, 'int'); return $val;}, $fields);
		$this->_options['projection'] = $project;
		return $this;
	}
	
	public function setRawReturn($enabled) {
		$this->getRaw = $enabled;
		
		return $this;
	} 

	/**
     * @throws \MongoCursorException
     */
    protected function errorIfOpened()
    {
        if ($this->_iterator === null) {
            return;
        }
        throw new Exception('cannot modify cursor after beginning iteration.');
    }
	
	protected function doQuery(){
		$command = $this->_command;
        try {
			if(method_exists($this->_collection, $command)){
				$this->_cursor = $this->_collection->$command($this->_query, $this->_options);
				$this->_iterator = new IteratorIterator($this->_cursor);
				$this->_iterator->rewind();
			}
            
        } catch (\MongoDB\Driver\Exception\ExecutionTimeoutException $e) {
            throw new MongoCursorTimeoutException($e->getMessage(), $e->getCode(), $e);
        }

	}
	
	public function getNext()
    {
        return $this->next();
    }
}
