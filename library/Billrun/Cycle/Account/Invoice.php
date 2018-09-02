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
	 * If true need to override data in billrun collection, 
	 * @var boolean
	 */
	protected $overrideMode = true;
	/**
	 * Hold all the discounts that are  applied to the account.
	 * @var array 
	 */
	protected $discounts= array();

	protected $invoicedLines = array();

	/**
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
		if (isset($options['override_mode'])) {
			$this->overrideMode = $options['override_mode'];
		}
		$this->aid = $options['aid'];
		$this->key = $options['billrun_key'];
		$force = (isset($options['autoload']) && $options['autoload']);
		$this->load($force, $options);
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
	protected function load($force, $options = FALSE) {
		$this->loadData();
		if (!$this->data->isEmpty() && !$this->overrideMode) {
			$this->exists = !$force;
			return;
		}
		$invoiceId = null;
		if ($this->overrideMode && !$this->data->isEmpty()) {
			$invoiceId = isset($this->data['invoice_id']) ? $this->data['invoice_id'] : null;
		}
		$this->reset($invoiceId, $options);
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
			'vat' => $vat, //TODO remove 2017-01-29
			'billrun_key' => $billrun_key,
		);
	}

	public function getBillrunKey() {
		return $this->key;
	}

	public function applyDiscounts() {
		$dm = new Billrun_DiscountManager();
		$this->discounts = $dm->getEligibleDiscounts($this);
		$sidDiscounts = array();
		foreach($this->discounts as $discount) {
			foreach($this->subscribers as  $subscriber) {
				if($subscriber->getData()['sid'] == $discount['sid']) {
					$rawDiscount = $discount->getRawData();
					$subscriber->updateInvoice(array('credit'=> $rawDiscount['aprice']), $rawDiscount, $rawDiscount, !empty($rawDiscount['tax_data']));			
					$sidDiscounts[$discount['sid']][] =$discount;
					continue 2;
				}
			}
		}
		foreach($this->subscribers as  $subscriber) {
			$sid = $subscriber->getData()['sid'];
			if( !empty($sidDiscounts[$subscriber->getData()['sid'] ]) ) {
				$subscriber->aggregateLinesToBreakdown($sidDiscounts[$sid]);
			}
		}
		$this->aggregateIntoInvoice(Billrun_Factory::config()->getConfigValue('billrun.invoice.aggregate.added_data',array()));
		$this->updateTotals();
	}
        
	
	/**
	 * 
	 * @param type $subLines
	 */
	public function aggregateIntoInvoice($untranslatedAggregationConfig) {
		$translations = array('BillrunKey' => $this->data['billrun_key'], 'Aid'=>$this->data['aid']);
		$aggregationConfig  = json_decode(Billrun_Util::translateTemplateValue(json_encode($untranslatedAggregationConfig),$translations),JSON_OBJECT_AS_ARRAY);
		$aggregate = new Billrun_Utils_Arrayquery_Aggregate();
		$rawData = $this->data->getRawData();
		foreach($aggregationConfig as $addedvalueKey => $aggregateConf) {
				$aggrResults = Billrun_Factory::Db()->getCollection($aggregateConf['collection'])->aggregate($aggregateConf['pipeline'])->setRawReturn(true);
				if($aggrResults) {
					foreach($aggrResults as $aggregateValue) {
						$rawData['added_data'][$addedvalueKey][] = $aggregateValue;
					}
			}
		}
		$this->data->setRawData($rawData);
	}
	
	/**
	 * Closes the billrun in the db by creating a unique invoice id
	 * @param int $invoiceId minimum invoice id to start from
	 */
	public function close($invoiceId,$isFake = FALSE,$customCollName = FALSE) {
		if(!$this->isAccountActive()) {
			Billrun_Factory::log("Deactivated account: " . $this->aid, Zend_Log::INFO);
			return;
		}
		$invoiceRawData = $this->getRawData();
		
		$rawDataWithSubs = $this->setSubscribers($invoiceRawData);
		if (!$isFake ) {
			$newRawData = $this->setInvoiceID($rawDataWithSubs, $invoiceId, $customCollName);
		} else {
			$rawDataWithSubs['invoice_id'] = $invoiceId;
			$newRawData = $rawDataWithSubs;
		}
		$this->data->setRawData($newRawData);		

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
	
	public function shouldKeepLinesinMemory($recordCount = 0) {
		return max($recordCount,count($this->subscribers)) < Billrun_Factory::config()->getConfigValue('billrun.max_subscribers_to_keep_lines',50);
	}
	
	/**
	 * Sets the id to the raw data
	 * @param array $invoiceRawData - Raw data to calculate id by
	 * @param integer $invoiceId - Min invoice id
	 * @return array Raw data with the invoice id
	 */
	protected function setInvoiceID(array $invoiceRawData, $invoiceId, $customCollName = FALSE) {
		if( !$this->overrideMode || !isset($invoiceRawData['invoice_id'])  ) {
			$autoIncKey = $invoiceRawData['billrun_key'] . "_" . $invoiceRawData['aid'];
			$currentId = $this->billrun_coll->createAutoInc(array('aid' => $invoiceRawData['aid'], 'billrun_key' => $invoiceRawData['billrun_key']), $invoiceId, $customCollName);
			$invoiceRawData['invoice_id'] = $currentId;
		}
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
		
		$newTotals = array(
			'before_vat' => 0,
			'after_vat' => 0,
			'after_vat_rounded' => 0,
			'vatable' => 0,
			'flat' => array('before_vat' => 0, 'after_vat' => 0, 'vatable' => 0),
			'service' => array('before_vat' => 0, 'after_vat' => 0, 'vatable' => 0),
			'usage' => array('before_vat' => 0, 'after_vat' => 0, 'vatable' => 0),
			'refund' => array('before_vat' => 0, 'after_vat' => 0, 'vatable' => 0),
			'charge' => array('before_vat' => 0, 'after_vat' => 0, 'vatable' => 0),
			'discount' => array('before_vat' => 0, 'after_vat' => 0, 'vatable' => 0),
			'past_balance' => array('after_vat' => 0),
		);
		foreach ($this->subscribers as $sub) {
			$newTotals = $sub->updateTotals($newTotals);
		}
		//Add the past balance to the invoice document if it will decresse the amount to pay to cover the invoice
		$pastBalance = Billrun_Bill::getTotalDueForAccount($this->getAid());
		if($pastBalance['total'] < -Billrun_Billingcycle::PRECISION  ) {
			$newTotals['past_balance']['after_vat'] = $pastBalance['total'];
		}
		$rawData['totals'] = $newTotals;
		$this->data->setRawData($rawData);
	}

	/**
	 * Resets the billrun data. If an invoice id exists, it will be kept.
	 */
	public function reset($invoiceId, $options) {
		$this->exists = false;
		$empty_billrun_entry = $this->getAccountEmptyBillrunEntry($this->aid, $this->key);
		$id_field = (isset($this->data['_id']) ? array('_id' => $this->data['_id']->getMongoID()) : array());
		if (!empty($invoiceId)) {
			$empty_billrun_entry['invoice_id'] = $invoiceId;
		}
		$rawData = array_merge($empty_billrun_entry, $id_field);
		$this->data = new Mongodloid_Entity($rawData, $this->billrun_coll);
		
		$this->initInvoiceDates($options);
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
	
	public function save() {
		if(!$this->isAccountActive()) {
			Billrun_Factory::log("Deactivated account: {$this->aid} no need to create invoice.", Zend_Log::DEBUG);
			return;
		}
		$ret = $this->billrun_coll->save($this->data);
		if (!$ret) {
			Billrun_Factory::log("Failed to create invoice for account " . $this->aid, Zend_Log::INFO);
		} else {
			Billrun_Factory::log("Created invoice " . $ret . " for account " . $this->aid, Zend_Log::INFO);
		}
	}
	
	//-----------------------------------------------------------
	
	/**
	 * Init the date values of the invoice.
	 */
	protected function initInvoiceDates($options) {
		$billrunDate = Billrun_Billingcycle::getEndTime($this->getBillrunKey());
		$initData = $this->data->getRawData();
		$initData['creation_time'] = new MongoDate(time());
		$initData['invoice_date'] = new MongoDate(strtotime(Billrun_Factory::config()->getConfigValue('billrun.invoicing_date', "first day of this month"), $billrunDate));
		$initData['end_date'] = new MongoDate($billrunDate);
		$initData['start_date'] = new MongoDate(Billrun_Billingcycle::getStartTime($this->getBillrunKey()));
		$initData['due_date'] =  new MongoDate( (@$options['attributes']['invoice_type'] == 'immediate') ? 
										strtotime(Billrun_Factory::config()->getConfigValue('billrun.immediate_due_date_interval', "+0 seconds"),$initData['creation_time']->sec - 1) :
										strtotime(Billrun_Factory::config()->getConfigValue('billrun.due_date_interval', "+14 days"), $billrunDate));
		$this->data->setRawData($initData);
	}
        
    //======================================================
    
	function isAccountActive() {
		$hasUsageLines = !$this->lines->query(['aid'=>$this->aid,'billrun'=>$this->key,'usaget'=>['$nin'=>['flat']]])->cursor()->limit(1)->current()->isEmpty();
		return !empty(array_filter($this->subscribers ,function($sub){ return !empty($sub->getData()['sid']);})) || !empty(array_filter($this->data['subs'] ,function($sub){ return !empty($sub['sid']);})) || $hasUsageLines;
	}

	public function getAid() {
		return $this->aid;
	}

	public function getSubscribers() {
		return $this->subscribers;
	}

	public function getTotals() {
		return $this->data['totals'];
	}

	public function getAppliedDiscounts() {
		return $this->discounts;
	}
	
	public function getInvoicedLines() {
	if(!$this->shouldKeepLinesinMemory()) {
		return FALSE;
	}
		$invoicedLines =  $this->invoicedLines;
		foreach($this->subscribers as $subscriber) {
			$invoicedLines += $subscriber->getInvoicedLines(); //+ works as the array is  actually hashed by the line stamp
		}
		return $invoicedLines;
	}
}
