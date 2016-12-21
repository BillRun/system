<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi model for operations on BillRun entities
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Entity {

	/**
	 * The DB collection name
	 * @var string
	 */
	protected $collectionName;

	/**
	 * The DB collection
	 * @var Mongodloid_Collection
	 */
	protected $collection;

	/**
	 * The entity billapi configuration
	 * @var array
	 */
	protected $config;

	/**
	 * The wanted query
	 * @var array
	 */
	protected $query = array();

	/**
	 * The new data
	 * @var array
	 */
	protected $update = array();

	/**
	 * The wanted sort (for get operations)
	 * @var array
	 */
	protected $sort = array();
	
	/**
	 * Page number for get operations
	 * @var int
	 */
	protected $page = 0;

	/**
	 * Page size for get operations
	 * @var int
	 */
	protected $size = 10;

	public function __construct($params) {
		$this->collectionName = $params['collection'];
		$this->collection = Billrun_Factory::db()->{$this->collectionName . 'Collection'}();
		$this->config = Billrun_Factory::config()->getConfigValue('billapi.' . $this->collectionName, array());
		foreach (array('query', 'update', 'sort') as $operation) {
			if (isset($params[$operation])) {
				$this->{$operation} = $params[$operation];
			}
		}
		$page = Billrun_Util::getFieldVal($params['page'], 0);
		$this->page = Billrun_Util::IsIntegerValue($page)? $page : 0;
		$size = Billrun_Util::getFieldVal($params['size'], 10);
		$this->size = Billrun_Util::IsIntegerValue($size)? $size : 10;
	}

	/**
	 * Create a new entity
	 * @param type $data the entity to create
	 * @return boolean
	 * @throws Billrun_Exceptions_Api
	 */
	public function create() {
		unset($this->update['_id']);
		if ($this->duplicateCheck($this->update)) {
			$status = $this->insert($this->update);
			$this->trackChanges($this->update['_id']);
			return isset($status['ok']) && $status['ok'];
		} else {
			throw new Billrun_Exceptions_Api(0, array(), 'Entity already exists');
		}
	}

	/**
	 * Inserts a document to the DB, as is
	 * @param array $data
	 */
	protected function insert($data) {
		return $this->collection->insert($data, array('w' => 1));
	}

	/**
	 * Returns true iff current record does not overlap with existing records in the DB
	 * @param array $data
	 * @param array $ignoreIds
	 * @return boolean
	 */
	protected function duplicateCheck($data, $ignoreIds = array()) {
		$query = array();
		foreach (Billrun_Util::getFieldVal($this->config['duplicate_check'], []) as $fieldName) {
			$query[$fieldName] = $data[$fieldName];
		}
		if ($ignoreIds) {
			$query['_id'] = array(
				'$nin' => $ignoreIds,
			);
		}
		return $query ? !$this->collection->query($query)->count() : TRUE;
	}

	/**
	 * Performs the update action by a query and data to update
	 * @param array $query
	 * @param array $data
	 */
	public function update() {
		$status = $this->dbUpdate($this->query, $this->update);
		if (!isset($status['nModified']) || !$status['nModified']) {
			return false;
		}
		return true;
	}

	/**
	 * method to close the current entity and open a new one (for track changes of entities)
	 * 
	 * @return mixed array of insert status, on failure false
	 * 
	 * @todo avoid overlapping of entities
	 */
	public function closeandnew() {
		if (!isset($this->update['from'])) {
			return false;
		}
		$closeAndNewPreUpdateOperation = array(
			'$set' => array(
				'to' => new MongoDate($this->update['from']->sec-1)
			)
		);
		$res = $this->collection->update($this->query, $closeAndNewPreUpdateOperation);
		if (!isset($res['nModified']) || !$res['nModified']) {
			return false;
		}

		$oldId = $this->query['_id'];
		unset($this->update['_id']);
		$status = $this->insert($this->update);
		$newId = $this->update['_id'];
		$this->trackChanges($newId, $oldId, isset($this->update['key']) ? 'key' : 'name');
		return isset($status['ok']) && $status['ok'];
	}
	
	/**
	 * method to track changes with audit trail
	 * 
	 * @param MongoId $newId the new id; if null take from update array _id field
	 * @param MongoId $oldId the old id; if null this is new document (insert operation)
	 * @param string $field the field name that will be used as the key
	 * 
	 * @return boolean true on success else false
	 */
	protected function trackChanges($newId = null, $oldId = null, $field = 'name') {
		if (is_null($newId) && isset($this->update['_id'])) {
			$newId = $this->update['_id'];
		}
		
		try {
			$user = Billrun_Factory::user();
			$logEntry = array(
				'source' => 'audit',
				'type' => 'change', // TODO: change to insert, close&new, remove, etc
				'urt' => new MongoDate(),
				'user' => array(
					'oid' => $user->getMongoId()->getMongoID(),
					'name' => $user->getUsername(),
				),
				'collection' => $this->collectionName,
				'old_oid' => $oldId,
				'new_oid' => $newId,
				'key' => isset($this->update[$field]) ? $this->update[$field] : null,
			);
			$logEntry['stamp'] = Billrun_Util::generateArrayStamp($logEntry);
			Billrun_Factory::db()->logCollection()->save(new Mongodloid_Entity($logEntry));
			return true;
		} catch (Exception $ex) {
			Billrun_Factory::log('Failed on insert to audit trail. ' . $ex->getCode() . ': ' . $ex->getMessage(), Zend_Log::ERR);
		}
		return false;
	}

	/**
	 * DB update currently limited to update of one record
	 * @param type $query
	 * @param type $data
	 */
	protected function dbUpdate($query, $data) {
		unset($data['_id']);
		$update = array(
			'$set' => $data,
		);
		return $this->collection->update($query, $update);
	}

	/**
	 * Gets an entity by a query
	 * @param array $query
	 * @param array $data
	 * @return array the entities found
	 */
	public function get() {
		if (isset($this->config['active_documents']) && $this->config['active_documents']) {
			$add_query = Billrun_Utils_Mongo::getDateBoundQuery();
			$this->query = array_merge($add_query, $this->query);
		}
		$ret = $this->runQuery($this->query, $this->sort);
		if (isset($this->config['get']['columns_filter_out']) && count($this->config['get']['columns_filter_out'])) {
			$filter_columns = $this->config['get']['columns_filter_out'];
			array_walk($ret, function(&$item) use ($filter_columns) {
				$item = array_diff_key($item, array_flip($filter_columns));
			});
		}
		return $ret;
	}

	/**
	 * Run a DB query against the current collection
	 * @param array $query
	 * @return array the result set
	 */
	protected function runQuery($query, $sort) {
		$res = $this->collection->find($query);
		
		if ($this->page != -1) {
			$res->skip($this->page * $this->size);
		}
		
		if ($this->size != -1) {
			$res->limit($this->size);
		}
		
		if ($sort) {
			$res = $res->sort($sort);
		}
		return array_values(iterator_to_array($res));
	}

	/**
	 * Deletes an entity by a query
	 * @param array $query
	 * @param array $update
	 * @return type
	 */
	public function delete() {
		if (!$this->query || empty($this->query)) { // currently must have some query
			return;
		}
		$this->remove($this->query); // TODO: check return value (success to remove?)
		$this->trackChanges(null, $this->query['_id'], $this->collectionName == 'rates' ? 'key' : 'name'); // assuming remove by _id
	}

	/**
	 * Performs a delete from the DB by a query
	 * @param array $query
	 */
	protected function remove($query) {
		$this->collection->remove($query);
	}

}
