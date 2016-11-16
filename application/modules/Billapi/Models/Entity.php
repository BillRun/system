<?php

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

	public function __construct($params) {
		$this->collectionName = $params['collection'];
		$this->collection = Billrun_Factory::db()->{$this->collectionName . 'Collection'}();
		$this->config = Billrun_Factory::config()->getConfigValue('billapi.' . $this->collectionName, array());
	}
	
	/**
	 * Create a new entity
	 * @param type $data the entity to create
	 * @return boolean
	 * @throws Billrun_Exceptions_Api
	 */
	public function create($data) {
		if ($this->duplicateCheck($data)) {
			$this->insert($data);
		}
		else {
			throw new Billrun_Exceptions_Api(0, array(), 'Username already exists');
		}
		return TRUE;
	}
	
	protected function insert($data) {
		$this->collection->insert($data);
	}
	
	/**
	 * Returns true iff current record does not overlap with existing records in the DB
	 * @param array $data
	 */
	protected function duplicateCheck($data) {
		$query = array();
		foreach (Billrun_Util::getFieldVal($this->config['duplicate_check'], []) as $fieldName) {
			$query[$fieldName] = $data[$fieldName];
		}
		return $query? !$this->collection->query($query)->count() : TRUE;
	}

}
