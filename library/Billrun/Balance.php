<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing abstract subscriber class
 *
 * @package  Billing
 * @since    0.5
 */
class Billrun_Balance implements ArrayAccess {

	/**
	 * Type of object
	 *
	 * @var string
	 */
	static protected $type = 'balance';

	/**
	 * Data container for subscriber details
	 * 
	 * @var array
	 */
	protected $data = array();

	public function __construct($options = array()) {
		if (isset($options['subscriber_id']) && isset($options['billrun_key'])) {
			$this->load($options['subscriber_id'], $options['billrun_key']);
		}
	}

	/**
	 * method to set values in the loaded balance.
	 */
	public function __set($name, $value) {
		//if (array_key_exists($name, $this->data)) {
		$this->data[$name] = $value;
		//}
		return $this->data[$name];
	}

	/**
	 * method to get public field from the data container
	 * 
	 * @param string $name name of the field
	 * @return mixed if data field  accessible return data field, else null
	 */
	public function __get($name) {
		//if (array_key_exists($name, $this->data)) {
		return $this->data->get($name);
		//}
	}

	/**
	 * Pass function calls to the mongo entity that is used to hold  our data.
	 * @param type $name the name of the called funtion
	 * @param type $arguments the function arguments
	 * @return mixed what ever the  mongo entity returns
	 */
	public function __call($name, $arguments) {
		return call_user_func_array(array($this->data, $name), $arguments);
	}

	/**
	 * method to save balance details
	 */
	public function load($subscriberId, $billrunKey = NULL) {

		$billrunKey = !$billrunKey ? Billrun_Util::getNextChargeKey(time()) : $billrunKey;

		$this->data = Billrun_Factory::db()->balancesCollection()->query(array(
				'subscriber_id' => $subscriberId,
				'billrun_month' => $billrunKey
			))->cursor()->limit(1)->current();

		$this->data->collection(Billrun_Factory::db()->balancesCollection());
	}

	/**
	 * method to save balance details
	 */
	public function save() {
		return $this->data->save(Billrun_Factory::db()->balancesCollection());
	}

	/**
	 * method to check if the loaded balance is valid
	 */
	public function isValid() {
		return !is_array($this->data) && count($this->data->getRawData()) > 0;
	}

	/**
	 * Create a new subscriber in a given month and load it (if none exists).
	 * @param type $billrun_month
	 * @param type $subscriber_id
	 * @param type $plan
	 * @param type $account_id
	 * @return boolean
	 */
	public function create($billrunKey, $subscriber, $plan_ref) {
		$ret = FALSE;
		$balances_coll = Billrun_Factory::db()->balancesCollection();
		$query = array(
			'subscriber_id' => $subscriber->subscriber_id,
			'billrun_month' => $billrunKey,
		);
		$update = array(
			'$setOnInsert' => self::getEmptySubscriberEntry($billrunKey, $subscriber->account_id, $subscriber->subscriber_id, $plan_ref),
		);
		$options = array(
			"upsert" => true,
		);
		$output = $balances_coll->update($query, $update, $options);
		if ($output['ok'] && isset($output['upserted'])) {
			Billrun_Factory::log('Added subscriber ' . $subscriber->subscriber_id . ' to balances collection', Zend_Log::INFO);
			$ret = true;
		}

		$this->load($subscriber->subscriber_id, $billrunKey);
		return $ret;
	}

	/**
	 * get a new subscriber array to be place in the DB.
	 * @param type $billrun_month
	 * @param type $account_id
	 * @param type $subscriber_id
	 * @param type $current_plan
	 * @return type
	 */
	public function getEmptySubscriberEntry($billrun_month, $account_id, $subscriber_id, $plan_ref) {
		return array(
			'billrun_month' => $billrun_month,
			'account_id' => $account_id,
			'subscriber_id' => $subscriber_id,
			'current_plan' => $plan_ref,
			'balance' => self::getEmptyBalance("intl_roam_"),
			'tx' => new stdclass,
		);
	}

	/**
	 * Check  if  a given  balnace exists.
	 * @param type $subscriberId (optional)
	 * @param type $billrunKey (optional)
	 * @return boolean
	 */
	protected function isExists($subscriberId, $billrunKey) {

		$blnce = Billrun_Factory::db()->balancesCollection()->query(array(
				'subscriber_id' => $subscriberId,
				'billrun_month' => $billrunKey
			))->cursor()->current();

		if (!count($blnce->getRawData())) {
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * * Get an empty balance structure
	 * @param string $prefix if supplied, usage types with this prefix would also be included
	 * @return array containing an empty balance structure.
	 */
	static public function getEmptyBalance($prefix = null) {
		$ret = array(
			'totals' => array(),
			'cost' => 0,
		);
		$usage_types = array('call', 'sms', 'data', 'incoming_call', 'incoming_sms', 'mms');
		if (!is_null($prefix)) {
			foreach ($usage_types as $usage_type) {
				$usage_types[] = $prefix . $usage_type;
			}
		}
		foreach ($usage_types as $usage_type) {
			$ret['totals'][$usage_type] = self::getEmptyUsageTypeTotals();
		}
		return $ret;
	}

	/**
	 * Get an empty plan usage counters.
	 * @return array containing an empty plan structure.
	 */
	static public function getEmptyUsageTypeTotals() {
		return array(
			'usagev' => 0,
			'cost' => 0,
		);
	}

	//=============== ArrayAccess Implementation =============
	public function offsetExists($offset) {
		return isset($this->data[$offset]);
	}

	public function offsetGet($offset) {
		return $this->__get($offset);
	}

	public function offsetSet($offset, $value) {
		return $this->__set($offset, $value, true);
	}

	public function offsetUnset($offset) {
		unset($this->data[$offset]);
	}

}