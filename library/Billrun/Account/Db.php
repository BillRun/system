<?php

/**
 * 
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing account class based on database
 *
 * @package  Billing
 * @since    5.0
 */
class Billrun_Account_Db extends Billrun_Account {

	/**
	 * Instance of the subscribers collection.
	 */
	protected $collection;

	protected static $type = 'db';
	
	protected static $queryBaseKeys = ['type', 'id', 'time', 'limit'];

	/**
	 * Construct a new account DB instance.
	 * @param array $options - Array of initialization parameters.
	 */
	public function __construct($options = array()) {
		parent::__construct($options);
		br_yaf_register_autoload('Models', APPLICATION_PATH . '/application/modules/Billapi');
		$this->collection = Billrun_Factory::db()->subscribersCollection();		
	}

	/**
	 * magic method for get cache value
	 * 
	 * @param string $key the key in the cache container
	 * 
	 * @return mixed the value in the cache
	 */
	public function __get($key) {
		if (isset($this->data[$key])) {
			return $this->data[$key];
		}
		return null;
	}

	/**
	 * Overrides parent abstract method
	 */
	protected function getAccountsDetails($query, $globalLimit = FALSE, $globalDate = FALSE) {
		$cursor =  $this->collection->query($query)->cursor();
		if($globalLimit) {
			$cursor->limit($globalLimit);
		}
		return $cursor;
	}


	public function getBillable(\Billrun_DataTypes_MongoCycleTime $cycle, $page = 0 , $size = 100, $aids = [], $invoicing_days = null) {
		$subsActiveQuery =Billrun_Utils_Mongo::getOverlappingWithRange('from', 'to', $cycle->start()->sec, $cycle->end()->sec);
		//
		$accountsQuery = array_merge(['type' => 'account'],$subsActiveQuery);;
		if(!empty($aids)) {
			$accountsQuery['aid'] = ['$in' => $aids ];
		}

		if (!empty($invoicing_days)) {
			$config = Billrun_Factory::config();
			/*if one of the searched "invoicing_day" is the default one, then we'll search for all the accounts with "invoicing_day"
			field that is different from all the undeclared invoicing_days. */
			$negativeSearch = in_array(strval($config->getConfigChargingDay()), $invoicing_days);
			$inDayOp = $negativeSearch ? '$nin' : '$in';
			$daysToInovice = $negativeSearch ?  array_values(array_diff(array_map('strval', range(1, 28)), $invoicing_days)) : $invoicing_days;
			$accountsQuery['invoicing_day'] = [ $inDayOp =>  $daysToInovice ];
		}

		$activeAidsRevs = $this->collection->query($accountsQuery)->cursor()->fields(['aid'])->sort(['aid'=>1])->skip($page * $size)->limit($size);
		$activeAids = array_values(array_map(function($ar) { return $ar['aid'];},iterator_to_array($activeAidsRevs)));
		$finalQuery = array_merge(['aid'=> ['$in' =>$activeAids ] ], $subsActiveQuery);
		$results = $this->collection->query($finalQuery)->cursor()->sort([	'from' => -1]);
		return $results;

	}

	/**
	 * Overrides parent abstract method
	 */
	protected function getAccountDetails($queries, $globalLimit = FALSE, $globalDate = FALSE) {
		$accounts = [];
		foreach ($queries as &$query) {
			$query = $this->buildParams($query);
			if (isset($query['limit'])) {
				$limit = $query['limit'];
				unset($query['limit']);
			}

			if (isset($query['time'])) {
				$time = Billrun_Utils_Mongo::getDateBoundQuery(strtotime($query['time']));
				$query = array_merge($query, $time);
				unset($query['time']);
			}

			if (isset($query['id'])) {
				$id = $query['id'];
				unset($query['id']);
			}
			$result = $this->collection->query($query)->cursor();
			if (isset($limit) && $limit === 1) {
				$account = $result->limit(1)->current();
				if ($account->isEmpty()) {
					continue;
				}
				if (isset($id)) {
					$account->set('id', $id);
				}
				$accounts[] = $account;
			} else {
				$accountsForQuery = iterator_to_array($this->collection->query($query)->cursor());
				if (empty($accountsForQuery)) {
					continue;
				}
				foreach ($accountsForQuery as $account) {
					if (isset($id)) {
						$account->set('id', $id);
					}
					$accounts[] = $account;
				}
			}
		}
		return $accounts;
	}

	public function permanentChange($query, $update) {
		$params = array(
			'collection' => 'accounts',
			'request' => array(
				'action' => 'permanentchange',
				'update' => json_encode($update),
				'query' => json_encode($query),
			)
		);
		$entityModel = Models_Entity::getInstance($params);
		$entityModel->permanentchange();
		}

	/**
	 * 
	 * Method to Save as 'Close And New' item
	 * @param Array $set_values Key value array with values to set
	 * @param Array $remove_values Array with keys to unset
	 */
	public function closeAndNew($set_values, $remove_values = array()) {

		// Updare old item
		$update = array('to' => new Mongodloid_Date());
		try {
			$this->collection->update(array('_id' => $this->data['_id']), array('$set' => $update));
		} catch (Exception $exc) {
			Billrun_Factory::log("Unable to update (closeAndNew) subscriber AID: " . $this->data['aid'], Zend_Log::INFO);
			return FALSE;
		}

		// Save new item
		if (!isset($set_values['from'])) {
			$set_values['from'] = new Mongodloid_Date();
		}
		if (!isset($set_values['to'])) {
			$set_values['to'] = new Mongodloid_Date(strtotime('+100 years'));
		}
		$newEntityData = array_merge($this->data, $set_values);
		foreach ($remove_values as $remove_filed_name) {
			unset($newEntityData[$remove_filed_name]);
		}
		unset($newEntityData['_id']);
		$newEntity = new Mongodloid_Entity($newEntityData);
		try {
			$ret = $this->collection->insert($newEntity);
			return !empty($ret['ok']);
		} catch (Exception $exc) {
			Billrun_Factory::log("Unable to insert (closeAndNew) subscriber AID: " . $this->data['aid'], Zend_Log::INFO);
			return FALSE;
		}
	}

	protected function buildParams($query) {
		$type = 'account';
		$query['type'] = $type;
		foreach ($query as $key => $value) {
			if (!in_array($key, static::$queryBaseKeys)) {
				$query[$key] = $value;
				continue;
		}
			switch ($key) {
				default:
		}
	}
	
		return $query;
	}
}
