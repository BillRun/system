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
 * @since    1.0
 */
class Billrun_Db extends Mongodloid_Db {
	
	/**
	 * constant of log collection name
	 */
	const log_table = 'log';

	/**
	 * constant of lines collection name
	 */
	const lines_table = 'lines';

	/**
	 * constant of billrun collection name
	 */
	const billrun_table = 'billrun';

	/**
	 * constant of events collection name
	 */
	const events_table = 'events';

	/**
	 * method to override the base getInstance
	 * 
	 * @return Billrun_Db instance of the Database
	 */
	static public function getInstance() {
		$config = Billrun_Factory::config();
		$conn = Billrun_Connection::getInstance($config->db->host, $config->db->port);
		return $conn->getDB($config->db->name);
	}
	
	/**
	 * method to create simple aggregation function over MongoDB
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
	 * get lines collection
	 * 
	 * @return Mongodloid_Collection Base collection object
	 */
	public function getLinesCollection() {
		return $this->getCollection(Billrun_Db::lines_table);
	}

	/**
	 * get billrun collection
	 * 
	 * @return Mongodloid_Collection Base collection object
	 */
	public function getBillrunCollection() {
		return $this->getCollection(Billrun_Db::billrun_table);
	}

	/**
	 * get log collection
	 * 
	 * @return Mongodloid_Collection Base collection object
	 */
	public function getLogCollection() {
		return $this->getCollection(Billrun_Db::log_table);
	}

}