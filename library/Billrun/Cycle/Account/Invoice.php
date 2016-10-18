<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Represents an aggregated account's invoice container
 *
 * @package  Cycle
 * @since    5.2
 */
class Billrun_Cycle_Account_Invoice {
	
	protected $aid;
	protected $key;
	
	/**
	 *
	 * @var Mongodloid_Entity
	 */
	protected $data;
	
	/**
	 * lines collection
	 * @var Mongodloid_Collection 
	 */
	protected $lines = null;

	/**
	 * billrun collection
	 * @var Mongodloid_Collection 
	 */
	protected $billrun_coll = null;

	/**
	 * True if the account already exists in billrun
	 * @var boolean
	 */
	protected $exists = false;
	
	/**
	 * Array of subscribers
	 * @var Billrun_Cycle_Subscriber_Invoice
	 */
	protected $subscribers = array();
	
	/**
	 * 
	 * @param type $options
	 * @todo used only in current balance API. Needs refactoring
	 */
	public function __construct($options = array()) {
		$this->lines = Billrun_Factory::db()->linesCollection();
		$this->billrun_coll = Billrun_Factory::db()->billrunCollection();
		$this->constructByOptions($options);
		$this->populateInvoiceWithAccountData($options['attributes']);
	}

	/**
	 * Construct the billrun with the input options
	 * @param array $options
	 */
	protected function constructByOptions($options) {
		if (!isset($options['aid'],$options['billrun_key'])) {
			Billrun_Factory::log("Returning an empty billrun!", Zend_Log::NOTICE);
			return;
		}
		
		$this->aid = $options['aid'];
		$this->key = $options['billrun_key'];
		$force = (isset($options['autoload']) && $options['autoload']);
		$this->load($force);
	}
	
	/**
	 * Return true if the account exists in the billrun.
	 * @return boolean true if exists.
	 */
	public function exists() {
		return $this->exists;
	}
	
	/**
	 * Load the billrun object, if already exists change the internal indication.
	 * @param boolean $force - If true, force the load.
	 */
	protected function load($force) {
		$this->loadData();
		if (!$this->data->isEmpty()) {
			$this->exists = !$force;
			return;
		}
		
		$this->reset();
	}
	
	/**
	 * Updates the billrun object to match the db
	 * @return true if already exists.
	 */
	protected function loadData() {
		$query = array(
					'aid' => $this->aid,
					'billrun_key' => $this->key);
		$cursor = $this->billrun_coll->query($query)->cursor(); 
		$this->data = $cursor->limit(1)->current();
		
		if($this->data->isEmpty()) {
			return false;
		}
		
		return true;
	}

	/**
	 * Add a subscriber to the current billrun entry.
	 * @param Billrun_Cycle_Subscriber_Invoice $subInvoice Subscriber to add.
	 */
	public function addSubscriber($subInvoice) {
		$this->subscribers[] = $subInvoice;
	}

	/**
	 * Get an empty billrun account entry structure.
	 * @param int $aid the account id of the billrun document
	 * @param string $billrun_key the billrun key of the billrun document
	 * @return array an empty billrun document
	 */
	public function getAccountEmptyBillrunEntry($aid, $billrun_key) {
		$vat = Billrun_Rates_Util::getVat();
		return array(
			'aid' => $aid,
			'subs' => array(
			),
			'vat' => $vat,
			'billrun_key' => $billrun_key,
		);
	}

	public function getBillrunKey() {
		return $this->key;
	}

	/**
	 * Closes the billrun in the db by creating a unique invoice id
	 * @param int $invoiceId minimum invoice id to start from
	 */
	public function close($invoiceId) {
		if(!$this->subscribers && !$this->data['subs']) {
			Billrun_Factory::log("Deactivated account: " . $this->aid, Zend_Log::INFO);
			return;
		}
		
		$invoiceRawData = $this->getRawData();
		
		$rawDataWithSubs = $this->setSubscribers($invoiceRawData);
		$newRawData = $this->setInvoicID($rawDataWithSubs, $invoiceId);
		$this->data->setRawData($newRawData);		

		$ret = $this->billrun_coll->save($this->data);
		if (!$ret) {
			Billrun_Factory::log("Failed to create invoice for account " . $this->aid, Zend_Log::INFO);
		} else {
			Billrun_Factory::log("Created invoice " . $ret . " for account " . $this->aid, Zend_Log::INFO);
		}
	}

	/**
	 * Set the subss to the raw data array
	 * @param array $invoiceRawData - Input array
	 * @return array with subscribers
	 */
	protected function setSubscribers(array $invoiceRawData) {
		// Add the subscribers.
		$invoiceSubs = Billrun_Util::getFieldVal($invoiceRawData['subs'], array());
		foreach ($this->subscribers as $currSub) {
			$invoiceSubs[] = $currSub->getData();
		}
		
		$invoiceRawData['subs'] = $invoiceSubs;
		return $invoiceRawData;
	}
	
	/**
	 * Sets the id to the raw data
	 * @param array $invoiceRawData - Raw data to calculate id by
	 * @param integer $invoiceId - Min invoice id
	 * @return array Raw data with the invoice id
	 */
	protected function setInvoicID(array $invoiceRawData, $invoiceId) {
		$autoIncKey = $invoiceRawData['billrun_key'] . "_" . $invoiceRawData['aid'];
		$currentId = $this->billrun_coll->createAutoInc($autoIncKey, $invoiceId);

		$invoiceRawData['invoice_id'] = $currentId;
		return $invoiceRawData;
	}
	
	/**
	 * Gets the current billrun document raw data
	 * @return Mongodloid_Entity
	 */
	public function getRawData() {
		return $this->data->getRawData();
	}

	/**
	 * Add pricing data to the account totals.
	 */
	public function updateTotals() {
		$rawData = $this->data->getRawData();
		/*

		  if ($vatable) {
		  $rawData['totals']['vatable'] = $pricingData['aprice'];
		  $vat = self::getVATByBillrunKey($billrun_key);
		  $price_after_vat = $pricingData['aprice'] + $pricingData['aprice'] * $vat;
		  } else {
		  $price_after_vat = $pricingData['aprice'];
		  }
		  $rawData['totals']['before_vat'] =  $this->getFieldVal($rawData,array('totals','before_vat'),0 ) + $pricingData['aprice'];
		  $rawData['totals']['after_vat'] =  $this->getFieldVal($rawData['totals'],array('after_vat'), 0) + $price_after_vat;
		  $rawData['totals']['vatable'] = $pricingData['aprice'];
		 */
		$newTotals = array('before_vat' => 0, 'after_vat' => 0, 'after_vat_rounded' => 0, 'vatable' => 0, 
			'flat' => array('before_vat' => 0, 'after_vat' => 0, 'vatable' => 0), 
			'service' => array('before_vat' => 0, 'after_vat' => 0, 'vatable' => 0), 
			'usage' => array('before_vat' => 0, 'after_vat' => 0, 'vatable' => 0)
		);
		foreach ($this->subscribers as $sub) {
			$newTotals = $sub->updateTotals($newTotals);
		}
		$rawData['totals'] = $newTotals;
		$this->data->setRawData($rawData);
	}

	/**
	 * Resets the billrun data. If an invoice id exists, it will be kept.
	 */
	public function reset() {
		$this->exists = false;
		$empty_billrun_entry = $this->getAccountEmptyBillrunEntry($this->aid, $this->key);
		$id_field = (isset($this->data['_id']) ? array('_id' => $this->data['_id']->getMongoID()) : array());
		$rawData = array_merge($empty_billrun_entry, $id_field);
		$this->data = new Mongodloid_Entity($rawData, $this->billrun_coll);
		
		$this->initInvoiceDates();
	}
	
	/**
	 * Get an empty billrun account entry structure.
	 * @return array an empty billrun document
	 */
	public function populateInvoiceWithAccountData($attributes) {
		$rawData = $this->data->getRawData();
		$rawData['attributes'] = $attributes;
		$this->data->setRawData($rawData);
	}
	
	/**
	 * Init the date values of the invoice.
	 */
	protected function initInvoiceDates() {
		$billrunDate = Billrun_Billingcycle::getEndTime($this->getBillrunKey());
		$initData = $this->data->getRawData();
		$initData['creation_time'] = new MongoDate(time());
		$initData['invoice_date'] = new MongoDate(strtotime(Billrun_Factory::config()->getConfigValue('billrun.invoicing_date', "first day of this month"), $billrunDate));
		$initData['end_date'] = new MongoDate($billrunDate);
		$initData['start_date'] = new MongoDate(Billrun_Billingcycle::getStartTime($this->getBillrunKey()));
		$initData['due_date'] = new MongoDate(strtotime(Billrun_Factory::config()->getConfigValue('billrun.due_date_interval', "+14 days"), $billrunDate));
		$this->data->setRawData($initData);
	}
}
