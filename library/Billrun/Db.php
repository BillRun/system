<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
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
		// TODO: refatoring the collections to factory (loose coupling)
		$this->collections = Billrun_Factory::config()->getConfigValue('db.collections', array());
		$timeout = Billrun_Factory::config()->getConfigValue('db.timeout', 3600000); // default 60 minutes
		if ($this->compareClientVersion('1.5.3', '<')) {
			Billrun_Factory::log('Set database cursor timeout to: ' . $timeout, Zend_Log::INFO);
			@MongoCursor::$timeout = $timeout;
		} else {
			// see also bugs: 
			// https://jira.mongodb.org/browse/PHP-1099
			// https://jira.mongodb.org/browse/PHP-1080
			$db->setWriteConcern($db->getWriteConcern()['w'], $timeout);
		}
	}
	
	/**
	 * Get the current MongoDB
	 * @return MongoDB
	 */
	public function getDb() {
		return $this->_db;
	}

	/**
	 * Method to override the base getInstance
	 * 
	 * @return Billrun_Db instance of the Database
	 */
	public static function getInstance($config) {
		$host = isset($config['host']) ? $config['host'] : '';
		if (isset($config['port'])) {
			$conn = Billrun_Connection::getInstance($host, $config['port']);
		} else {
			$conn = Billrun_Connection::getInstance($host);
		}

		if (!isset($config['options'])) {
			return $conn->getDB($config['name'], $config['user'], $config['password']);
		}

		return $conn->getDB($config['name'], $config['user'], $config['password'], $config['options']);
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
		$collection = $this->getCollection($collection_name);
		return $collection->aggregate(array('$match' => $where), array('$group' => $group), array('$match' => $having));
	}

	/**
	 * Magic method to receive collection instance
	 * 
	 * @param string $name name of the function call; convention is collectionnameCollection
	 * @param array $arguments not used for collectionnameCollection (forward compatability)
	 * 
	 * @return mixed if collection exists return instance of Mongodloid_Collection, else false
	 */
	public function __call($name, $arguments) {
		$suffix = 'Collection';
		if (substr($name, (-1) * strlen($suffix)) == $suffix) {
			$collectionName = substr($name, 0, (strpos($name, $suffix)));
			if (!empty($this->collections[$collectionName])) {
				return $this->getCollection($this->collections[$collectionName]);
			}
		} else if ($arguments['force']) {
			return $this->getCollection($name);
		}

		Billrun_Factory::log('Collection or property ' . $name . ' did not found in the DB layer', Zend_Log::ALERT);
		return false;
	}

	/**
	 * get collections  for the database.
	 * @param type $name the name of the colleaction to retrieve.
	 * @return type the requested collection
	 */
	public function __get($name) {
		if (!empty($this->collections[$name])) {
			return $this->collections[$name];
		}
		Billrun_Factory::log('Collection or property ' . $name . ' did not found in the DB layer', Zend_Log::ALERT);
	}

	public function execute($code, $args = array()) {
		return $this->command(array('$eval' => $code, 'args' => $args));
	}

	/**
	 * Change numeric references to MongoDate object in a given filed in an array.
	 * @param MongoDate $arr 
	 * @param type $fieldName the filed in the array to alter
	 * @return the translated array
	 */
	public static function intToMongoDate($arr) {
		if (is_array($arr)) {
			foreach ($arr as $key => $value) {
				if (is_numeric($value)) {
					$arr[$key] = new MongoDate((int) $value);
				}
			}
		} else if (is_numeric($arr)) {
			$arr = new MongoDate((int) $arr);
		}
		return $arr;
	}
	
	public function getByDBRef($dbRef) {
		if(MongoDBRef::isRef($dbRef)) {
			$coll = $this->getCollection($dbRef['$ref']);
			if($coll) {
				return $coll->getRef($dbRef);
			}
		}
		
		return FALSE;
	}

}
