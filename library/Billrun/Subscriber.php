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
abstract class Billrun_Subscriber extends Billrun_Base {

	/**
	 * Type of object
	 *
	 * @var string
	 */
	static protected $type = 'subscriber';

	/**
	 * Data container for subscriber details
	 * 
	 * @var array
	 */
	protected $data = array();

	/**
	 * the fields that are accessible to public
	 * 
	 * @var array
	 */
	protected $availableFields = array();

	public function __construct($options = array()) {
		parent::__construct($options);
		if (isset($options['availableFields'])) {
			$this->availableFields = $options['availableFields'];
		}
	}

	/**
	 * method to load subsbscriber details
	 */
	public function __set($name, $value) {
		if (in_array($name, $this->availableFields) && array_key_exists($name, $this->data)) {
			$this->data[$name] = $value;
		}
		return null;
	}
	
	/**
	 * method to receive public properties of the subscriber
	 * 
	 * @return array the available fields for the subscriber
	 */
	public function getAvailableFields() {
		return $this->availableFields;
	}

	/**
	 * method to get public field from the data container
	 * 
	 * @param string $name name of the field
	 * @return mixed if data field  accessible return data field, else null
	 */
	public function __get($name) {
		if (in_array($name, $this->availableFields) && array_key_exists($name, $this->data)) {
			return $this->data[$name];
		}
		return null;
	}

	/**
	 * method to load subsbscriber details
	 * 
	 * @param array $params load by those params 
	 */
	abstract public function load($params);

	/**
	 * method to save subsbscriber details
	 */
	abstract public function save();

	/**
	 * method to delete subsbscriber entity
	 */
	abstract public function delete();
	
	/**
	 * method to check if the subscriber is valid
	 */
	abstract public function isValid();
	
	
		/**
	 * Get subscriber information for a certain month
	 * @param type $subscriberId (optional)
	 * @param type $billrunKey (optional)
	 * @return boolean
	 */
	public function getBalance( $billrunKey = null, $subscriberId = null ) {
		$billrunKey = !$billrunKey ? Billrun_Util::getNextChargeKey(time()) : $billrunKey;
		$subscriberId = !$subscriberId ? $this->data['subscriber_id'] : $subscriberId;
		
		$subcr = Billrun_Factory::db()->balancesCollection()->query(array(
						'subscriber_id' => $subscriberId, 
						'billrun_month' => $billrunKey
					 ))->cursor()->current();
		
		if(!count($subcr->getRawData())) {
			return FALSE;
		}
		return $subcr;
	}
	
	/**
	 * Create aa new subscriber in a given month.
	 * @param type $billrun_month
	 * @param type $subscriber_id
	 * @param type $plan
	 * @param type $account_id
	 * @return boolean
	 */
	public function createBalance($billrunKey, $subscriberId, $plan_ref, $accountId) {
		if(!$this->getBalance( $billrunKey, $subscriberId)) {
			Billrun_Factory::log('Adding subscriber ' .  $subscriberId . ' to balances collection', Zend_Log::INFO);
			$newSubscriber = new Mongodloid_Entity(self::getEmptySubscriberEntry($billrunKey, $accountId, $subscriberId, $plan_ref));
			$newSubscriber->collection(Billrun_Factory::db()->balancesCollection());
			$newSubscriber->save();
			return $this->getBalance( $billrunKey, $subscriberId );
		} 
		return FALSE;
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
			'balance' => array(
				'usage_counters' => array(
					'call' => 0,
					'incoming_call' => 0,
					'sms' => 0,
					'mms' => 0,
					'data' => 0,
					'inter_roam_incoming_call' => 0,
					'inter_roam_call' => 0,
					'inter_roam_callback' => 0,
					'inter_roam_sms' => 0,
					'inter_roam_data' => 0,
					'inter_roam_incoming_sms' => 0,
				),
				'current_charge' => 0,
			),
		);
	}
}