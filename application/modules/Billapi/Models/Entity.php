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
 * @since    0.5
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

	public function __construct($params) {
		$this->collectionName = $params['collection'];
		$this->collection = Billrun_Factory::db()->{$this->collectionName . 'Collection'}();
		$this->config = Billrun_Factory::config()->getConfigValue('billapi.' . $this->collectionName, array());
		foreach (array('query', 'update', 'sort') as $operation) {
			if (isset($params[$operation])) {
				$this->{$operation} = $params[$operation];
			}
		}
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
			$this->insert($this->update);
		} else {
			throw new Billrun_Exceptions_Api(0, array(), 'Username already exists');
		}
	}

	/**
	 * Inserts a document to the DB, as is
	 * @param array $data
	 */
	protected function insert($data) {
		$this->collection->insert($data);
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
		$this->dbUpdate($this->query, $this->update);
	}

	/**
	 * DB update currently limited to update of one record
	 * @param type $query
	 * @param type $data
	 */
	protected function dbUpdate($query, $data) {
		unset($data['_id']);
		$updateMethod = Billrun_Util::getFieldVal($this->config['update_method'], 'update');
		if ($updateMethod == 'update') {
			$update = array(
				'$set' => $data,
			);
			$res = $this->collection->update($query, $update);
		} else if ($updateMethod == 'close_and_new') {
			
		}
	}

	/**
	 * Gets an entity by a query
	 * @param array $query
	 * @param array $data
	 * @return array the entities found
	 */
	public function get() {
		return $this->query($this->query, $this->sort);
	}

	/**
	 * Run a DB query against the current collection
	 * @param array $query
	 * @return array the result set
	 */
	protected function query($query, $sort) {
		$res = $this->collection->find($query);
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
		if (!$this->query) { // currently must have some query
			return;
		}
		$this->remove($this->query);
	}

	/**
	 * Performs a delete from the DB by a query
	 * @param array $query
	 */
	protected function remove($query) {
		$this->collection->remove($query);
	}

}
