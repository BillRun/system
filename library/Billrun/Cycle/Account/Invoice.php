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
	use Billrun_Traits_ConditionsCheck;
	
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

	protected $invoicedLines = array();

	protected $totalGroupHashMap = array();
	protected $groupingSumExtraFields = array();
	protected $groupingEnabled = true;

	protected $aggregationTranslations = [];
	protected $constructOptions = [];
	protected $useMongoTransactions = false;


        /**
	 * @todo used only in current balance API. Needs refactoring
	 */
	public function __construct($options = array()) {
		$this->lines = Billrun_Factory::db()->linesCollection();
		$this->billrun_coll = Billrun_Factory::db()->billrunCollection();
		$this->constructByOptions($options);
		$this->populateInvoiceWithAccountData($options['attributes']);
		$this->initInvoiceDates();
		$this->groupingEnabled = Billrun_Factory::config()->getConfigValue('billrun.grouping.enabled', true);
		$this->groupingSumExtraFields = Billrun_Factory::config()->getConfigValue('billrun.grouping.sum_fields', array());
		$this->useMongoTransactions = Billrun_Factory::config()->getConfigValue('customer.aggregator.db_transactions', false);
		$this->constructOptions = $options;
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
	 * 
	 * @param string $billrunDate
	 * @return Mongodloid_Date
	 */
	protected function generateDueDate($billrunDate, $initData = []) {
		$options = Billrun_Factory::config()->getConfigValue('billrun.due_date', []);
		$invoiceType = isset($this->data['attributes']['invoice_type']) ? $this->data['attributes']['invoice_type'] : null; 
		foreach ($options as $option) {
			if ($option['anchor_field'] == 'invoice_date' && $this->isConditionsMeet(array('invoice_type' => $invoiceType), $option['conditions'])) { //TODO: transfer the entity instead of just array with invoice_type
				 return new Mongodloid_Date(Billrun_Util::calcRelativeTime($option['relative_time'], $billrunDate));										  // once BRCD-2351 is fixed
			}
			if (!empty($initData[$option['anchor_field']]) && $initData[$option['anchor_field']] instanceof Mongodloid_Date &&
				$this->isConditionsMeet(array('invoice_type' => $invoiceType), $option['conditions'])) {
					return new Mongodloid_Date(Billrun_Util::calcRelativeTime($option['relative_time'], $initData[$option['anchor_field']]->sec));
			}
			if (!empty($this->data[$option['anchor_field']]) && $this->data[$option['anchor_field']] instanceof Mongodloid_Date &&
				$this->isConditionsMeet(array('invoice_type' => $invoiceType), $option['conditions'])) {
					return new Mongodloid_Date(Billrun_Util::calcRelativeTime($option['relative_time'], $this->data[$option['anchor_field']]->sec));
			}
		}
		Billrun_Factory::log()->log('Failed to match due_date for aid:' . $this->getAid() . ', using default configuration', Zend_Log::DEBUG);
		return new Mongodloid_Date(strtotime(Billrun_Factory::config()->getConfigValue('billrun.due_date_interval', '+14 days'), $billrunDate));
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
                        'hostname' => Billrun_Util::getHostName(),
		);
	}

	public function getBillrunKey() {
		return $this->key;
	}
	
	/**
	 * Apply discount added to the account to subscribers;
	 */
	public function applyDiscounts($discounts) {
		$sidDiscounts = array();
		foreach($discounts as $discount) {
			foreach($this->subscribers as  $subscriber) {
				$subscriberData = $subscriber->getData();
				if($subscriberData['sid'] == $discount['sid']) {
					$rawDiscount = ( $discount instanceof Mongodloid_Entity ) ? $discount->getRawData() : $discount ;
					if (Billrun_Utils_Plays::isPlaysInUse()) {
						$discount['subscriber'] = array('play' => isset($subscriberData['play']) ? $subscriberData['play'] : Billrun_Utils_Plays::getDefaultPlay()['name']);
					}
					$subscriber->updateInvoice(array('credit'=> $rawDiscount['aprice']), $rawDiscount, $rawDiscount, !empty($rawDiscount['tax_data']));
					$sidDiscounts[$discount['sid']][] =$discount;
					continue 2;
				}
			}
		}
		foreach($this->subscribers as  $subscriber) {
			$sid = $subscriber->getData()['sid'];
			if( !empty($sidDiscounts[$subscriber->getData()['sid'] ]) ) {
				$subscriber->aggregateLinesToBreakdown($sidDiscounts[$sid], true);
			}
		}
		$configValue = !empty(Billrun_Factory::config()->getConfigValue('billrun.invoice.aggregate.account.added_data',array())) ?  Billrun_Factory::config()->getConfigValue('billrun.invoice.aggregate.account.added_data') : [];
		$this->aggregateIntoInvoice($configValue);
		$this->updateTotals();
	}

	public function addConfigurableData() {
		Billrun_Factory::dispatcher()->trigger('beforeAddInvoiceConfigurableData', array($this, &$this->data));
		$this->aggregateIntoInvoice(Billrun_Factory::config()->getConfigValue('billrun.invoice.aggregate.account.final_data',array()));
		Billrun_Factory::dispatcher()->trigger('afterAddInvoiceConfigurableData', array($this, &$this->data));
	}
	/**
	 * 
	 * @param type $subLines
	 */
	public function aggregateIntoInvoice($untranslatedAggregationConfig) {
		$invoiceData = $this->data->getRawData();
		$accountLastMonthlyBillrun = Billrun_Billrun::getAccountLastMonthlyBillrun($invoiceData['aid'], $invoiceData['billrun_key']);
		$followingBillrunKey = Billrun_Billingcycle::getFollowingBillrunKey($accountLastMonthlyBillrun['billrun_key'] ?? null);
		$this->aggregationTranslations = array_merge($this->aggregationTranslations,[
			'BillrunKey' => $invoiceData['billrun_key'],
			'Aid' => $invoiceData['aid'],
			'StartTime' => $invoiceData['start_date']->sec,
			'EndTime' => $invoiceData['end_date']->sec,
			'NextBillrunKey' => Billrun_Billingcycle::getFollowingBillrunKey($invoiceData['billrun_key']),
			'PreviousBillrunKey' => Billrun_Billingcycle::getPreviousBillrunKey($invoiceData['billrun_key']),
			'NextNextBillrunKey' => Billrun_Billingcycle::getFollowingBillrunKey(Billrun_Billingcycle::getFollowingBillrunKey($invoiceData['billrun_key'])),
			'NextBillrunKeyOfLastMonthlyBillrun' => $followingBillrunKey,
		]);
		$aggregationConfig  = json_decode(Billrun_Util::translateTemplateValue(json_encode($untranslatedAggregationConfig), $this->aggregationTranslations),JSON_OBJECT_AS_ARRAY);
		$aggregate = new Billrun_Utils_Arrayquery_Aggregate();
		foreach($aggregationConfig as $addedvalueKey => $aggregateConf) {
			foreach ($aggregateConf['pipelines'] as $pipeline) {
				 Billrun_Utils_Mongo::convertQueryMongodloidDates($pipeline);
				if (empty($aggregateConf['use_db'])) {
					$aggrResults = $aggregate->aggregate( $pipeline, [$invoiceData]);
				} else {
					$aggrResults = Billrun_Factory::Db()->getCollection($aggregateConf['collection'])->aggregate($pipeline)->setRawReturn(true);
				}
				if($aggrResults) {
					foreach($aggrResults as $aggregateValue) {
						$invoiceData['added_data'][$addedvalueKey][] = $aggregateValue;
					}
			}
		}
		}
		$this->data->setRawData($invoiceData);
	}
	
	/**
	 * Closes the billrun in the db by creating a unique invoice id
	 * @param int $invoiceId minimum invoice id to start from
	 */
	public function close($invoiceId,$isFake = FALSE,$customCollName = FALSE) {
		Billrun_Factory::log('closing invoice.', Zend_Log::DEBUG);
		if(!$this->isAccountActive()) {
			Billrun_Factory::log("Deactivated account: " . $this->aid, Zend_Log::INFO);
			return;
		}
		$invoiceRawData = $this->getRawData();
		
		if (Billrun_Factory::config()->getConfigValue('billrun.save_subs', true, "bool")) {
			$invoiceRawData = $this->setSubscribers($invoiceRawData);
		}
		if (!$isFake ) {
			$newRawData = $this->setInvoiceID($invoiceRawData, $invoiceId, $customCollName);
		} else {
			$invoiceRawData['invoice_id'] = $invoiceId;
			$newRawData = $invoiceRawData;
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
	 * Add user fields data to the invoice.
	 */
	public function setUserFields(array $user_fields) {
		$invoiceRawData = $this->getRawData();
		$invoiceRawData['uf'] = $user_fields;
		$this->data->setRawData($invoiceRawData);
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
                $this->totalGroupHashMap = array();
		Billrun_Factory::log('Updating totals.', Zend_Log::DEBUG);
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
			'current_balance' => array('after_vat' => 0),
		);
		Billrun_Factory::log('updating totals based on: '. count($this->subscribers) .' subscribers.', Zend_Log::INFO);
		foreach ($this->subscribers as $sub) {
			$newTotals = $sub->updateTotals($newTotals);
                        if($this->groupingEnabled){
                                Billrun_Util::setIn($newTotals, 'grouping', $this->sumUpGroupingTotalForAccount(Billrun_Util::getIn($newTotals, 'grouping', array()),
                                        Billrun_Util::getIn($sub->getTotals(),'grouping', array())));
                        }
		}
		
		$invoicingDay = Billrun_Billingcycle::getDatetime($rawData['billrun_key']);
		
		//Add the past balance to the invoice document if it will decresse the amount to pay to cover the invoice
		$config = Billrun_Factory::config();
		$pastBalanceConfig = $config->getConfigValue('billrun.past_balance', []);
		$past_balance_date = $invoicingDay;
		if (!empty($pastBalanceConfig) && !empty($rawData[$pastBalanceConfig['anchor_field']])) {
			$relative_date = date ('Ymd' , Billrun_Util::calcRelativeTime($pastBalanceConfig['relative_time'],date($rawData[$pastBalanceConfig['anchor_field']]->sec)));
			$past_balance_date = $relative_date . "000000";
		}
		$pastBalance = Billrun_Bill::getTotalDueForAccount($this->getAid(), $past_balance_date);
		if(!Billrun_Util::isEqual($pastBalance['total'], 0, Billrun_Billingcycle::PRECISION)) {
			$newTotals['past_balance']['after_vat'] = $pastBalance['total'];
			$newTotals['past_balance']['without_waiting']['after_vat'] = $pastBalance['without_waiting'];
		}
		$newTotals['current_balance']['after_vat'] = $newTotals['past_balance']['after_vat'] + $newTotals['after_vat_rounded'];
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
		
	}
	
	/**
	 * Get an empty billrun account entry structure.
	 * @return array an empty billrun document
	 */
	public function populateInvoiceWithAccountData($attributes) {
		$config = Billrun_Factory::config();
		$rawData = $this->data->getRawData();
		$rawData['attributes'] = $attributes;
		if ($config->isMultiDayCycle()) {
			$this->setInvoicingDay($rawData, $attributes);
		}
		$this->data->setRawData($rawData);
	}
	
	public function save() {
		if(!$this->isAccountActive()) {
			Billrun_Factory::log("Deactivated account: {$this->aid} no need to create invoice.", Zend_Log::DEBUG);
			return;
		}
		
		$rawData = $this->data->getRawData();
		if ($this->useMongoTransactions) {
			if (Billrun_Factory::db()->compareServerVersion('4.2.0', '>=') && !Billrun_Factory::db()->isStandalone()) {
				$this->_saveWithTransaction($rawData);
			} else {
				Billrun_Factory::log("Mongo transactions enabled for cycle but not supported on this version. Proceeding without transaction.", Zend_Log::WARN);
				$this->_saveWithoutTransaction($rawData);
			}
		} else {
			$this->_saveWithoutTransaction($rawData);
		}
	}
	
	//-----------------------------------------------------------
	
	/**
	 * Init the date values of the invoice.
	 */
	protected function initInvoiceDates() {
		$initData = $this->data->getRawData();
		$invoicing_day = !empty($initData['invoicing_day']) ? $initData['invoicing_day'] : null;
		$billrunDate = Billrun_Billingcycle::getEndTime($this->getBillrunKey(), $invoicing_day);
		$initData['creation_time'] = new Mongodloid_Date(time());
		$isOneTimeInvoice = isset($initData['attributes']['invoice_type']) && $initData['attributes']['invoice_type'] == 'immediate' ? true : false;
		$invoiceDate = $isOneTimeInvoice ? strtotime($initData['billrun_key']) : strtotime(Billrun_Factory::config()->getConfigValue('billrun.invoicing_date', "first day of this month"), $billrunDate);
		$initData['invoice_date'] = new Mongodloid_Date($invoiceDate);
		$initData['end_date'] = new Mongodloid_Date($billrunDate);
		$initData['start_date'] = new Mongodloid_Date(Billrun_Billingcycle::getStartTime($this->getBillrunKey(), $invoicing_day));
		$initData['due_date'] = $this->generateDueDate($billrunDate, $initData);
		$chargeNotBefore = $this->generateChargeDate($initData);
		if (!empty($chargeNotBefore)) {
			$initData['charge'] = ['not_before' => $chargeNotBefore];
		}
		
		$this->data->setRawData($initData);
	}
        
    //======================================================
    
	function isAccountActive() {
		$ignoreSubsWithNoPlans = Billrun_Factory::config()->getConfigValue('billrun.ignore_no_plans_invoices',true);
		$hasActiveSubscribers = !empty(array_filter($this->subscribers ,function($sub) use ($ignoreSubsWithNoPlans) {
									$subData = $sub->getData();
									return !empty($subData['sid']) && (!$ignoreSubsWithNoPlans || !is_null($subData['totals']['flat']['after_vat'])) ;
								}))
							||
								!empty(array_filter($this->data['subs'] ,function($sub) use ($ignoreSubsWithNoPlans) {
									return !empty($sub['sid']) && (!$ignoreSubsWithNoPlans || !is_null($sub['totals']['flat']['after_vat']) );
								}));

		$overrideAccountValidation = false;
		Billrun_Factory::dispatcher()->trigger('isAccountActiveForInvoicing',[  &$overrideAccountValidation  , $this->data ]);

		if( $hasActiveSubscribers || $overrideAccountValidation ||
			!empty($this->data['totals']['after_vat_rounded'])  || !empty($this->constructOptions['force_active']) ) {
				return true;
		}
		$accountActivenessLinesHistory = Billrun_Factory::config()->getConfigValue("pricing.months_limit", 3);
		if (is_numeric($accountActivenessLinesHistory)) {
			$accountActivenessDate = strtotime($accountActivenessLinesHistory . ' months ago');
		} else {
			$accountActivenessDate = strtotime((string) $accountActivenessLinesHistory);
		}
		$query = [
			'aid'=>$this->aid,
			'urt' => ['$gte' => new Mongodloid_Date($accountActivenessDate)],
			'billrun'=>$this->key,
			'usaget'=>['$nin'=>['flat']],
		];
		$hasUsageLines = !$this->lines->query($query)->cursor()->limit(1)->current()->isEmpty();

		return $hasUsageLines;
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
	
	public function getInvoicedLines() {
	if(!$this->shouldKeepLinesinMemory()) {
		return FALSE;
	}
		$invoicedLines =  $this->invoicedLines;
		foreach($this->subscribers as $subscriber) {
			$invoicedLines += $subscriber->getInvoicedLines(); //+ *only* works as the array is  actually hashed by the line stamp
		}
		return $invoicedLines;
	}
	
	protected function generateChargeDate($initData) {
		$options = Billrun_Factory::config()->getConfigValue('charge.not_before', []);
		$invoiceType = @$this->data['attributes']['invoice_type'];
		
		// go through all config options and try to match the relevant
		foreach ($options as $option) {
			if (in_array($invoiceType, $option['invoice_type']) && $option['anchor_field'] == 'confirm_date') {
				return false;
			}
			
			if (!empty($initData[$option['anchor_field']]) && in_array($invoiceType, $option['invoice_type'])) {
				return new Mongodloid_Date(Billrun_Util::calcRelativeTime($option['relative_time'], $initData[$option['anchor_field']]->sec));
			}
		}
		
		// if no config option was matched this could be an on-confirmation invoice - use invoice 'due_date' field
		if (!empty($initData['due_date'])) {
			return $initData['due_date'];
		}
		
		// else - get config default value or temporerily use 'invoice_date' with offset
		Billrun_Factory::log()->log('Failed to match charge date for aid:' . $this->getAid() . ', using default configuration', Zend_Log::NOTICE);
		return new Mongodloid_Date(strtotime(Billrun_Factory::config()->getConfigValue('billrun.due_date_interval', '+14 days'), $initData['invoice_date']));
	}

	/**
	 * This function add to the account totals grouping the provided subscriber totals group.
	 * @param type $currentTotalGroups 
	 * @param type $subTotalGroups 
	 * @return the new sum up of the account totals grouping
	 */
	protected function sumUpGroupingTotalForAccount($currentTotalGroups, $subTotalGroups) {
		foreach ($subTotalGroups as $group) {
			$type = $group['grouping'];
			if (isset($group['sid'])) {
				continue;
			}
			$usagev = $group['usagev'];
			unset($group['usagev']);
			$count = $group['count'];
			unset($group['count']);
			$beforeTax = $group['before_taxes'];
			unset($group['before_taxes']);
			$taxes = $group['taxes'];
			unset($group['taxes']);
			$afterTax = $group['after_taxes'];
			unset($group['after_taxes']);
			$extraSumGroupData = [];
			// Unset extra sum grouping fields
			if(!empty($this->groupingSumExtraFields)){
				$groupingSumExtraFields = $this->groupingSumExtraFields;
			}else{
				$groupingSumExtraFields = static::getGroupingSumExtraFields($type);
			}
			foreach ($groupingSumExtraFields as $field) {
				Billrun_Util::setIn($extraSumGroupData, $field, Billrun_Util::getIn($group, $field, 0));
				Billrun_Util::unsetInPath($group, $field);
			}
			$stamp = Billrun_Util::generateArrayStamp($group, [], true);
			$index = Billrun_Util::getIn($this->totalGroupHashMap, $stamp, null);
			if (!isset($index)) {
				$index = count($currentTotalGroups);
				$currentTotalGroups[$index] = $group;
				$this->totalGroupHashMap[$stamp] = $index;
			}
			$currentTotalGroups[$index]['usagev'] = Billrun_Util::getFieldVal($currentTotalGroups[$index]['usagev'], 0) + $usagev;
			$currentTotalGroups[$index]['count'] = Billrun_Util::getFieldVal($currentTotalGroups[$index]['count'], 0) + $count;
			$currentTotalGroups[$index]['before_taxes'] = Billrun_Util::getFieldVal($currentTotalGroups[$index]['before_taxes'], 0) + $beforeTax;
			$currentTotalGroups[$index]['taxes'] = Billrun_Util::getFieldVal($currentTotalGroups[$index]['taxes'], 0) + $taxes;
			$currentTotalGroups[$index]['after_taxes'] = Billrun_Util::getFieldVal($currentTotalGroups[$index]['after_taxes'], 0) + $afterTax;
			// Sum extra grouping fields
			foreach ($groupingSumExtraFields as $field) {
				Billrun_Util::setIn($currentTotalGroups[$index], $field, Billrun_Util::getIn($currentTotalGroups[$index], $field, 0) + Billrun_Util::getIn($extraSumGroupData, $field, 0));
			}	
		}
		return $currentTotalGroups;
	}

	public static function getGroupingSumExtraFields($type){
		$groupingSumExtraFields = [];
		$grouping = Billrun_Factory::config()->getConfigValue('billrun.grouping', []);
		foreach($grouping as $groupingStructure){
			if($groupingStructure['name'] == $type){
				foreach ($groupingStructure['fields'] as $field) {
					if (!isset($field['field_name']) || !isset($field['op'])) {
						continue;
					}
					if ($field['op'] === 'sum') {
						$groupingSumExtraFields[] = $field['field_name'];
					}
				}
			}
			
		}
		return $groupingSumExtraFields;
	}
	

	
	public function setInvoicingDay(&$rawData, $attributes) {
		$config = Billrun_Factory::config();
		$rawData['invoicing_day'] = !empty($attributes['invoicing_day']) ? $attributes['invoicing_day'] : $config->getConfigChargingDay();
	}

	public function addAggragtionTranslations($translations) {
		$this->aggregationTranslations = array_merge($this->aggregationTranslations,$translations);
	}

	protected function _saveWithTransaction(array $rawData)
	{
		$billrun_subs_coll = Billrun_Factory::db()->billrun_subsCollection();
		$billrun_grouping_coll = Billrun_Factory::db()->billrun_groupingCollection();

		$allSubscribersToSave = [];
		$allGroupItemsToSave = [];

		if (!empty($rawData['subs'])) {
			foreach ($rawData['subs'] as &$subscriber) {
				if (isset($subscriber['totals']['grouping']) && is_array($subscriber['totals']['grouping'])) {
					foreach ($subscriber['totals']['grouping'] as $groupItem) {
						$groupItem['sid'] = $subscriber['sid'];
						$groupItem['billrun_key'] = $this->key;
						$groupItem['aid'] = $this->aid;
						$allGroupItemsToSave[] = new Mongodloid_Entity($groupItem);
					}
					unset($subscriber['totals']['grouping']);
				}
				$allSubscribersToSave[] = new Mongodloid_Entity($subscriber);
			}
		}

		$mainInvoiceData = $this->data;
		unset($mainInvoiceData['subs']);

		$session = Billrun_Factory::db()->startSession();
		$session->startTransaction();

		try {
			if (!empty($allSubscribersToSave)) {
				$billrun_subs_coll->batchInsert($allSubscribersToSave, ['session' => $session]);
			}
			if (!empty($allGroupItemsToSave)) {
				$billrun_grouping_coll->batchInsert($allGroupItemsToSave, ['session' => $session]);
			}

			$this->billrun_coll->save($mainInvoiceData, null, ['session' => $session]);

			$session->commitTransaction();
			Billrun_Factory::log("Created invoice " . $this->data['invoice_id'] . " for account " . $this->aid, Zend_Log::INFO);
			Billrun_Factory::dispatcher()->trigger('afterAccountInvoiceSaved', array($this->data, &$this));
		} catch (Exception $e) {
			$session->abortTransaction();
			$errorMessage = $e->getMessage();
			Billrun_Factory::log("Failed to create invoice for account {$this->aid}. Reason: " . $errorMessage, Zend_Log::ERR);
			throw $e;
		} finally {
			$session->endSession();
		}
	}

	protected function _saveWithoutTransaction(array $rawData)
	{
		$allSubscribersToSave = [];
		$allGroupItemsToSave = [];
		if (!empty($rawData['subs'])) {
			foreach ($rawData['subs'] as &$subscriber) {
				if (isset($subscriber['totals']['grouping']) && is_array($subscriber['totals']['grouping'])) {
					foreach ($subscriber['totals']['grouping'] as $groupItem) {
						$groupItem['sid'] = $subscriber['sid'];
						$groupItem['billrun_key'] = $this->key;
						$groupItem['aid'] = $this->aid;
						$allGroupItemsToSave[] = new Mongodloid_Entity($groupItem);
					}
					unset($subscriber['totals']['grouping']);
				}
				$allSubscribersToSave[] = new Mongodloid_Entity($subscriber);
			}
		}

		$mainInvoiceData = $this->data;
		unset($mainInvoiceData['subs']);
		$billrun_subs_coll = Billrun_Factory::db()->billrun_subsCollection();
		$billrun_grouping_coll = Billrun_Factory::db()->billrun_groupingCollection();

		try {
			if (!empty($allSubscribersToSave)) {
				$billrun_subs_coll->batchInsert($allSubscribersToSave);
			}
			if (!empty($allGroupItemsToSave)) {
				$billrun_grouping_coll->batchInsert($allGroupItemsToSave);
			}

			$this->billrun_coll->save($mainInvoiceData);

			Billrun_Factory::log("Created invoice " . $this->data['invoice_id'] . " for account " . $this->aid, Zend_Log::INFO);
			Billrun_Factory::dispatcher()->trigger('afterAccountInvoiceSaved', array($this->data, &$this));
		} catch (Exception $e) {
			$errorMessage = $e->getMessage();
			Billrun_Factory::log("Failed to create invoice for account {$this->aid}. Reason: " . $errorMessage, Zend_Log::ERR);
			$this->cleanupFailedCycleData($billrun_subs_coll, $billrun_grouping_coll, $this->billrun_coll);
			throw $e;
		}
	}

	protected function cleanupFailedCycleData($subsColl, $groupingColl, $billrunColl)
	{

		$billrunKey = $this->key;
		$accountId = $this->aid;

		try {
			$subsColl->remove([
				'key' => $billrunKey,
				'aid' => $accountId
			]);
		} catch (Exception $e) {
			Billrun_Factory::log("Cleanup failed for subscribers in account {$accountId}: " . $e->getMessage(), Zend_Log::ERR);
		}

		try {
			$groupingColl->remove([
				'billrun_key' => $billrunKey,
				'aid' => $accountId
			]);
		} catch (Exception $e) {
			Billrun_Factory::log("Cleanup failed for grouping items in account {$accountId}: " . $e->getMessage(), Zend_Log::ERR);
		}

		try {
			$billrunColl->remove([
				'billrun_key' => $billrunKey,
				'aid' => $accountId
			]);
		} catch (Exception $e) {
			Billrun_Factory::log("Cleanup failed for main invoice in account {$accountId}: " . $e->getMessage(), Zend_Log::ERR);
		}
	}

}
