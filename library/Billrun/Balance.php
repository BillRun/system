<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
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
	
	protected $collection = null;

	public function __construct($options = array()) {
		// TODO: refactoring the read preference to the factory to take it from config
		$this->collection = self::getCollection();

		if (isset($options['data'])) {
			$this->data = $options['data'];
		} else if (isset($options['sid']) && isset($options['billrun_key']) && isset($options['unique_plan_id'])) {
			$this->load($options['sid'], $options['billrun_key'], $options['unique_plan_id']);
		}

	}
	
	public static function getCollection() {
		return Billrun_Factory::db(array('name' => 'balances'))->balancesCollection()->setReadPreference('RP_PRIMARY');
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

	public function load($subscriberId, $billrunKey = NULL, $uniquePlanId) {
		Billrun_Factory::log()->log("Trying to load balance " . $billrunKey . " for subscriber " . $subscriberId . ', unique_plan_id=' . $uniquePlanId, Zend_Log::DEBUG);
		$billrunKey = !$billrunKey ? Billrun_Util::getBillrunKey(time()) : $billrunKey;

		$this->data = $this->collection->query(array(
				'sid' => $subscriberId,
				'billrun_month' => $billrunKey,
				'unique_plan_id' => $uniquePlanId
			))
			->cursor()->setReadPreference('RP_PRIMARY')
			->limit(1)->current();

		// set the data collection to enable clear save
		$this->data->collection($this->collection);
	}

	/**
	 * method to save balance details
	 */
	public function save() {
		return $this->data->save($this->collection);
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
	 * @param type $sid
	 * @param type $plan
	 * @param type $aid
	 * @return boolean
	 */
	public function create($billrunKey, $subscriber, $plan_ref) {
		$ret = self::createBalanceIfMissing($subscriber->aid, $subscriber->sid, $billrunKey, $plan_ref);
		$this->load($subscriber->sid, $billrunKey);
		return $ret;
	}

	/**
	 * Create a new balance  for a subscriber  in a given billrun
	 * @param type $account_id the account ID  of the subscriber.
	 * @param type $subscriber_id the subscriber ID.
	 * @param type $billrun_key the  billrun key that the balance refer to.
	 * @param type $plan_ref the subscriber plan.
	 * @param type $to end date of this package - if package exists.
	 * @return boolean true  if the creation was sucessful false otherwise.
	 */
	public static function createBalanceIfMissing($aid, $sid, $billrun_key, $plan_ref, $uniquePlanId = '', $from = null ,$to = null, $serviceId = null, $serviceName = null, $joinedField = null) {
		$ret = false;
//		$balances_coll = Billrun_Factory::db(array('name' => 'balances'))->balancesCollection();
		
		$query = array(
			'sid' => $sid,
			'billrun_month' => $billrun_key,
		);
		$data = self::getEmptySubscriberEntry($billrun_key, $aid, $sid, $plan_ref);
		if (!empty($uniquePlanId)) {
			$query['unique_plan_id'] = $data['unique_plan_id'] = $uniquePlanId;
		}
		if (!is_null($from)) {
			$data['from'] = new MongoDate($from);
		}
		if (!is_null($to)) {
			$data['to'] = new MongoDate($to);
		}
		if (!is_null($serviceId)) {
			$data['service_id'] = $serviceId;
		}
		if (!is_null($serviceName)) {
			$data['service_name'] = $serviceName;
		}
		if (!is_null($joinedField)) {
			$data['balance']['totals'][$joinedField]['usagev'] = 0;
			$data['balance']['totals'][$joinedField]['cost'] = 0;
			$data['balance']['totals'][$joinedField]['count'] = 0;
		}
		
		$update = array(
			'$setOnInsert' => $data,
		);
		$options = array(
			'upsert' => true,
			'new' => true,
			'w' => 1,
		);
		Billrun_Factory::log()->log("Create empty balance " . $billrun_key . " if not exists for subscriber " . $sid, Zend_Log::DEBUG);
		$output = self::getCollection()->findAndModify($query, $update, array(), $options, true);
		
		if ($output['ok'] && isset($output['value']) && $output['value']) {
			Billrun_Factory::log('Added balance ' . $billrun_key . ' to subscriber ' . $sid, Zend_Log::INFO);
			$ret = true;
		} else {
			Billrun_Factory::log('Error creating balance ' . $billrun_key . ' for subscriber ' . $sid . '. Output was: ' . print_r($output->getRawData(), true), Zend_Log::ALERT);
		}

		return $ret;
	}

	/**
	 * get a new subscriber array to be place in the DB.
	 * @param type $billrun_month
	 * @param type $aid
	 * @param type $sid
	 * @param type $current_plan
	 * @return type
	 */
	public static function getEmptySubscriberEntry($billrun_month, $aid, $sid, $plan_ref) {
		return array(
			'billrun_month' => $billrun_month,
			'aid' => $aid,
			'sid' => $sid,
			'current_plan' => $plan_ref,
			'balance' => self::getEmptyBalance("out_plan_"),
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

		$balance = $this->collection->query(array(
				'sid' => $subscriberId,
				'billrun_month' => $billrunKey
			))->cursor()->setReadPreference('RP_PRIMARY')
			->current();

		if (!count($balance->getRawData())) {
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * Get an empty balance structure
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
			'count' => 0,
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
