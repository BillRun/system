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
	public function __construct(\MongoDB\Database $db, \Mongodloid_Connection $connection) {
		parent::__construct($db, $connection);
		// TODO: refatoring the collections to factory (loose coupling)
		$this->collections = Billrun_Factory::config()->getConfigValue('db.collections', array());
		$timeout = Billrun_Factory::config()->getConfigValue('db.timeout', 3600000); // default 60 minutes
		// see also bugs: 
		// https://jira.mongodb.org/browse/PHP-1099
		// https://jira.mongodb.org/browse/PHP-1080
		$options = [
			'readPreference' => $this->_db->getReadPreference(),
			'writeConcern' => new \MongoDB\Driver\WriteConcern($this->_db->getWriteConcern()->getW() ?? 1, max($timeout, 0))
		];
		$this->_db = $this->_db->withOptions($options);
	}
	
	/**
	 * Get the current MongoDB\Database
	 * @return MongoDB\Database
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
                            $name = $this->collections[$collectionName];
			}else if ($arguments[0]['force']){
                            $name = $collectionName;
                        } else {
                            Billrun_Factory::log('Collection or property ' . $name . ' did not found in the DB layer', Zend_Log::ALERT);
                            return false;
                        }
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
	 * Change numeric references to MongodloidDate object in a given filed in an array.
	 * @param Mongodloid_Date $arr 
	 * @param type $fieldName the filed in the array to alter
	 * @return the translated array
	 */
	public static function intToMongodloidDate($arr) {
		if (is_array($arr)) {
			foreach ($arr as $key => $value) {
				if (is_numeric($value)) {
					$arr[$key] = new Mongodloid_Date((int) $value);
				}
			}
		} else if (is_numeric($arr)) {
			$arr = new Mongodloid_Date((int) $arr);
		}
		return $arr;
	}
	
	public function getByDBRef($dbRef) {
		if(Mongodloid_Ref::isRef($dbRef)) {
			$coll = $this->getCollection($dbRef['$ref']);
			if($coll) {
				return $coll->getRef($dbRef);
			}
		}
		
		return FALSE;
	}

}
