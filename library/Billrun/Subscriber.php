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

	/**
	 * extra fields for billrun
	 * @var array
	 */
	protected $billrunExtraFields = array();
	
	/**
	 * extra fields for the customer
	 * @var array
	 */
	protected $customerExtraData = array();

	public function __construct($options = array()) {
		parent::__construct($options);
		if (isset($options['availableFields'])) {
			$this->availableFields = $options['availableFields'];
		}
		if (isset($options['extra_data'])) {
			$this->customerExtraData = $options['extra_data'];
		}
	}

	/**
	 * method to load subsbscriber details
	 */
	public function __set($name, $value) {
		if (array_key_exists($name, $this->availableFields) && array_key_exists($name, $this->data)) {
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
		if ((array_key_exists($name, $this->availableFields) || in_array($name, $this->billrunExtraFields)) && array_key_exists($name, $this->data)) {
			return $this->data[$name];
		}
		else if (array_key_exists($name, $this->customerExtraData) && isset ($this->data['extra_data'][$name])) {
			return $this->data['extra_data'][$name];
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
	 * Get subscriber balance information for the current month.
	 * @param type $subscriberId (optional)
	 * @param type $billrunKey (optional)
	 * @return boolean
	 */
	public function getBalance() {
		return Billrun_Factory::balance()->load($this->data['sid'], Billrun_Util::getNextChargeKey(time()));
	}

	/**
	 * get the (paged) current account(s) plans by time
	 */
	abstract public function getList($page, $size, $time, $acc_id = null);

	/**
	 * get the list of active subscribers from a json file. Parse subscribers plans at the given time (unix timestamp)
	 */
	abstract public function getListFromFile($file_path, $time);

	abstract public function getSubscribersByParams($params, $availableFields);

	/**
	 * Returns field names to be saved when creating billrun
	 * @return array
	 */
	public function getExtraFieldsForBillrun() {
		return $this->billrunExtraFields;
	}
	
	/**
	 * Returns extra fields for the customer
	 * @return array
	 */
	public function getCustomerExtraData() {
		return $this->customerExtraData;
	}

	
	public function getSubscriberData() {
		return $this->data;
	}
}
