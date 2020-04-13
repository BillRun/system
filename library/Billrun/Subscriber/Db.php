<?php

/**
 * 
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing subscriber class based on database
 *
 * @package  Billing
 * @since    4.0
 * @todo This class sometimes uses Uppercase keys and sometimes lower case keys. [IMSI and imsi]. 
 * There should be a convertor in the set and get function so that the keys will ALWAYS be lower or upper.
 * This way whoever uses this class can send whatever he wants in the key fields.
 */
class Billrun_Subscriber_Db extends Billrun_Subscriber {

	/**
	 * True if the query handlers are loaded.
	 * @var boolean
	 */
	static $queriesLoaded = false;
	
	protected $collection;
	
	static protected $type = 'db';

	/**
	 * Construct a new subscriber DB instance.
	 * @param array $options - Array of initialization parameters.
	 */
	public function __construct($options = array()) {
		parent::__construct($options);	
		$this->collection = Billrun_Factory::db()->subscribersCollection();
	}
	
	protected function getSubscriberDetails($queries) {
		$subs = [];
		$type = 'subscriber';
		foreach ($queries as $query) {
			$query['type'] = $type;
			
			if (isset($query['time'])) {
				$time = Billrun_Utils_Mongo::getDateBoundQuery(strtotime($query['time']));
				$query = array_merge($query, $time);
				unset($query['time']);
			}

			if (isset($query['limit'])) {
				$limit = $query['limit'];
				unset($query['limit']);
			}

			if (isset($query['id'])) {
				$id = $query['id'];
				unset($query['id']);
			}

			if (isset($query['EXTRAS'])) {
				unset($query['EXTRAS']);
			}
			if (is_numeric($query['sid'])) {
				settype($query['sid'], 'int');
			}
			$result = $this->collection->query($query)->cursor();
			if (isset($limit) && $limit === 1) {
				$sub = $result->limit(1)->current();
				if ($sub->isEmpty()) {
					continue;
				}
				if (isset($id)) {
					$sub->set('id', $id);
				}
				$subs[] = $sub;
			} else {
				$subs[] = iterator_to_array($result);
			}
		}
		return $subs;
	}

	/**
	 * method to save subsbscriber details
	 */
	public function save() {
		return true;
	}

	/**
	 * method to delete subsbscriber entity
	 */
	public function delete() {
		return true;
	}

	public function isValid() {
		return true;
	}
	
	public function getCredits($billrun_key, $retEntity = false) {
		return array();
	}

}
