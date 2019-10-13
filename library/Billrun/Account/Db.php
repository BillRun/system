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
	 * Construct a new account DB instance.
	 * @param array $options - Array of initialization parameters.
	 */
	public function __construct($options = array()) {
		parent::__construct($options);
		Yaf_Loader::getInstance(APPLICATION_PATH . '/application/modules/Billapi')->registerLocalNamespace("Models");
	}

	public function getList($page, $size, $time, $acc_id = null) {
		
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
	 * Get accounts by transferred query.
	 * @param array $query - query.
	 */
	public function getAccountsByQuery($query) {
		return $this->collection->query($query)->cursor();
	}
	
	public function getQueryActiveAccounts($aids) {
		$today = new MongoDate();
		return array(
			'aid' => array('$in' => $aids), 
			'from' => array('$lte' => $today), 
			'to' => array('$gte' => $today), 
			'type' => "account"
		);
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
	
	protected function getAccountDetails($query) {
		return $this->collection->query($params)->cursor()->limit(1)->current();
	}
}
