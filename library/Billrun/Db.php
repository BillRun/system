<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing db class
 *
 * @package  Db
 * @since    0.5
 */
class Billrun_Db extends Mongodloid_Db {

	/**
	 * list of collections available in the DB
	 * 
	 * @var array
	 * @since 0.3
	 */
	protected $collections = array();

	/**
	 * 
	 * @param \MongoDb $db
	 * @param \Mongodloid_Connection $connection
	 */
	public function __construct(\MongoDb $db, \Mongodloid_Connection $connection) {
		parent::__construct($db, $connection);
		$this->collections = Billrun_Factory::config()->getConfigValue('db.collections', array());
		MongoCursor::$timeout = Billrun_Factory::config()->getConfigValue('db.timeout', 300000); // default 5 minutes
	}

	/**
	 * Method to override the base getInstance
	 * 
	 * @return Billrun_Db instance of the Database
	 */
	static public function getInstance() {
		$config = Billrun_Factory::config();
		$conn = Billrun_Connection::getInstance($config->db->host, $config->db->port);
		return $conn->getDB($config->db->name);
	}

	/**
	 * Method to create simple aggregation function over MongoDB
	 * 
	 * @param string $collection_name the collection name
	 * @param array $where the filter clause (before the aggregation)
	 * @param array $group the aggregation function
	 * @param array $having the filter clause on the results (after the aggregation)
	 * 
	 * @return array results of the aggregation
	 */
	public function simple_aggregate($collection_name, $where, $group, $having) {
		$collection = self::db()->getCollection($collection_name);
		return $collection->aggregate(array('$match' => $where), array('$group' => $group), array('$match' => $having));
	}

	/**
	 * Magic method to receive collection instance
	 * 
	 * @param string $name name of the function call; convention is getCollnameCollection
	 * @param array $arguments not used for getCollnameCollection
	 * @return mixed if collection exists return instance of Mongodloid_Collection, else false
	 */
	public function __call($name, $arguments) {
		$suffix = 'Collection';
		if (substr($name, (-1) * strlen($suffix)) == $suffix) {
			$collectionName = substr($name, 0, (strpos($name, $suffix)));
			if (in_array($collectionName, $this->collections)) {
				return $this->getCollection($this->collections[$collectionName]);
			}
		}
		return false;
	}

	/**
	 * get collections  for the database.
	 * @param type $name the name of the colleaction to retreive.
	 * @return type the requested collection
	 */
	public function __get($name) {
		if (in_array($name, $this->collections)) {
			return $this->collections[$name];
		}
		Billrun_Factory::log()->log('Collection or property' . $name . ' did not found in the DB layer', Zend_Log::ALERT);
	}

}