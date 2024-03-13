<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing aggregator class for customers records
 *
 * @package  calculator
 * @since    0.5
 */
class Billrun_Aggregator_Customer extends Billrun_Cycle_Aggregator {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'customer';

	/**
	 *
	 * @var int
	 */
	protected $page = null;

	/**
	 *
	 * @var int
	 */
	protected $size = 200;

	/**
	 *
	 * @var Mongodloid_Collection
	 */
	protected $plans = null;

	/**
	 *
	 * @var Mongodloid_Collection
	 */
	protected $lines = null;

	/**
	 *
	 * @var Mongodloid_Collection
	 */
	protected $billingCycle = null;

	/**
	 *
	 * @var Mongodloid_Collection
	 */
	protected $billrunCol = null;

	/**
	 *
	 * @var int invoice id to start from
	 */
	protected $min_invoice_id = 101;

	protected $rates;
	protected $testAcc = false;

	/**
	 * Memory limit in bytes, after which the aggregation is stopped. Set to -1 for no limit.
	 * @var int
	 */
	protected $memory_limit = -1;

	/**
	 * Accounts to override
	 * @var array
	 */
	protected $forceAccountIds = array();

	/**
	 * the amount of account lines to preload from the db at a time.
	 * @param int $bulkAccountPreload
	 */
	protected $bulkAccountPreload = 10;

	/**
	 * the account ids that were successfully aggregated
	 * @var array
	 */
	protected $successfulAccounts = array();

	/**
	 *
	 * @var int flag to represent if recreate_invoices
	 */

	protected $recreateInvoices = null;

	/**
	 * Manager for the aggregate subscriber logic.
	 * @var Billrun_Aggregator_Subscriber_Manager
	 */
	protected $subscriberAggregator;

	/**
	 *
	 * @var Billrun_DataTypes_Billrun
	 */
	protected $billrun;

	protected $accounts;

	/**
	 * True if Cycle process
	 * @var boolean
	 */
	protected $isCycle = false;

	/**
	 * If true then we can load the data.
	 * @var boolean
	 */
	protected $canLoad = false;

	/**
	 * If true need to override data in billrun collection,
	 * @var boolean
	 */
	protected $overrideMode;
	/**
	 *  Is the run is fake (for example to get a current balance in the middle of the month)
	 */
	public $fakeCycle = false;
	
	/**
	 * If false don't automatically generate pdf. 
	 * @var boolean
	 */
	protected $generatePdf = true;
	
	/**
	 * If true don't aggregate usage lines. 
	 * @var boolean
	 */
	public $ignoreCdrs = false;
        
        /**
	 * Array of invoicing days, extra customer filtration
	 * @var array
	 */
	public $invoicing_days = [];
	
	/**
	 * Is premature cycle's run is available.
	 * @var bollean
	 */
	public $allowPrematureRun = false;
	
	/**
	 * Is multi cycle day mode.
	 * @var bollean
	 */
	public $multiDayCycleMode = false;
	
/**
	 * Array of aggregation options.
	 * @var array.
	 */
        public $options;
        
        /**
	 * Array of aid => sids, to merge their credit installments.
	 * @var array.
	 */
        public $merge_credit_installments;

	public function __construct($options = array()) {
		$this->isValid = false;
		parent::__construct($options);

		ini_set('mongo.native_long', 1); //Set mongo  to use  long int  for  all aggregated integer data.

		if (isset($options['aggregator']['recreate_invoices']) && $options['aggregator']['recreate_invoices']) {
			$this->recreateInvoices = $options['aggregator']['recreate_invoices'];
		}
		$config = Billrun_Factory::config();
		$this->buildBillrun($options);

		if (isset($options['aggregator']['test_accounts'])) {
			$this->testAcc = $options['aggregator']['test_accounts'];
		}

		if (isset($options['aggregator']['memory_limit_in_mb'])) {
			if ($options['aggregator']['memory_limit_in_mb'] > -1) {
				$this->memory_limit = $options['aggregator']['memory_limit_in_mb'] * 1048576;
			} else {
				$this->memory_limit = $options['aggregator']['memory_limit_in_mb'];
			}
		}

		$this->size = (int) Billrun_Util::getFieldVal($options['aggregator']['size'],$this->size);
		//Override the configuration size settings with  the  size  provided in the arguments.
		$this->size = (int) Billrun_Util::getFieldVal($options['size'],$this->size);

		$this->bulkAccountPreload = (int) Billrun_Util::getFieldVal($options['aggregator']['bulk_account_preload'],$this->bulkAccountPreload);
		$this->min_invoice_id = (int) Billrun_Util::getFieldVal($options['aggregator']['min_invoice_id'],$this->min_invoice_id);
		$this->forceAccountIds =(array) Billrun_Util::getFieldVal($options['aggregator']['force_accounts'],  Billrun_Util::getFieldVal($options['force_accounts'],$this->forceAccountIds));
		$this->fakeCycle = Billrun_Util::getFieldVal($options['aggregator']['fake_cycle'], Billrun_Util::getFieldVal($options['fake_cycle'], $this->fakeCycle));
		$this->ignoreCdrs = Billrun_Util::getFieldVal($options['aggregator']['ignore_cdrs'], Billrun_Util::getFieldVal($options['ignore_cdrs'], $this->ignoreCdrs));
		$this->allowPrematureRun = $config->getConfigValue('cycle.allow_premature_run', false);
		
		if($this->multiDayCycleMode = $config->isMultiDayCycle()) {
			Billrun_Factory::log()->log("Running on multi cycle day mode", Zend_Log::INFO);
			$this->invoicing_days = $this->getInvoicingDays($options);
		} elseif(!empty($options['invoicing_days'])) {
				Billrun_Factory::log()->log("Multi cycle day mode is off, 'invoicing_days' parameter was ignored.", Zend_Log::WARN);
		}
		
		if (isset($options['action']) && $options['action'] == 'cycle') {
			$this->billingCycle = Billrun_Factory::db()->billing_cycleCollection();
			$this->isCycle = true;
		}

		if ((isset($options['aggregator']['page']) || isset($options['page'])) && !$this->isCycle) {
			$this->page = isset($options['page']) ? (int) $options['page'] : (int) $options['aggregator']['page'];
		}
		
		if (isset($options['generate_pdf'])) {
			$this->generatePdf = (filter_var($options['generate_pdf'], FILTER_VALIDATE_BOOLEAN) == false ? false : true);
		}
	
		if (!$this->shouldRunAggregate($options['stamp'])) {
			Billrun_Factory::log()->log("Can't run aggregate before end of billing cycle", Zend_Log::WARN);
			return;
		}

		$this->plans = Billrun_Factory::db()->plansCollection();
		$this->lines = Billrun_Factory::db()->linesCollection();
		$this->billrunCol = Billrun_Factory::db()->billrunCollection();
		$this->overrideMode = $this->getAggregatorConfig('override_mode', true);

		if (!$this->recreateInvoices && $this->isCycle){
			$pageResult = $this->getPage();
			if ($pageResult === FALSE) {
				return;
			}
			$this->page = $pageResult;
		}

		$aggregateOptions = array(
			'passthrough_fields' => $this->getAggregatorConfig('passthrough_data', array()),
			'subs_passthrough_fields' => $this->getAggregatorConfig('subscriber.passthrough_data', array()),
		);
		// If the accounts should not be overriden, filter the existing ones before.
		if (!$this->overrideMode) {
			// Get the aid exclusion query
			$aggregateOptions['exclusion_query'] = $this->billrun->existingAccountsQuery();
		}
		//This class will define the account/subscriber/plans aggregation logic for the cycle
		$this->aggregationLogic = Billrun_Account::getAccountAggregationLogic($aggregateOptions);

		$this->isValid = true;
                $this->merge_credit_installments = [];
	}

	public function getCycle() {
		return new Billrun_DataTypes_CycleTime($this->getStamp());
	}

	public function getPlans($account=null, $subscriber=null) {
		if(empty($this->plansCache)) {
			$pipelines[] = $this->aggregationLogic->getCycleDateMatchPipeline($this->getCycle());
			$pipelines[] = $this->aggregationLogic->getPlansProjectPipeline();
			$coll = Billrun_Factory::db()->plansCollection();
			$res = $this->aggregatePipelines($pipelines,$coll);
			$this->plansCache =  $this->toKeyHashedArray($res,'plan');
		}

		$localPlans = $this->overrideEntityValues($this->plansCache,@$account['overrides'],'plan');
		return $this->overrideEntityValues($localPlans,@$subscriber['overrides'],'plan');
	}

	public function getServices($account=null, $subscriber=null) {
		if(empty($this->servicesCache)) {
			$pipelines[] = $this->aggregationLogic->getCycleDateMatchPipeline($this->getCycle());
			$coll = Billrun_Factory::db()->servicesCollection();
			$res =  $this->aggregatePipelines($pipelines,$coll);
			$this->servicesCache = $this->toKeyHashedArray($res , 'name');
		}

		$localServices = $this->overrideEntityValues($this->servicesCache,@$account['overrides'],'service');
		return $this->overrideEntityValues($localServices,@$subscriber['overrides'],'service');
	}

	public function getRates($account=null, $subscriber=null) {
		if(empty($this->ratesCache)) {
			Billrun_Factory::log("Preparing rates cache", Zend_Log::DEBUG);
			$pipelines[] = $this->aggregationLogic->getCycleDateMatchPipeline($this->getCycle());
			$coll = Billrun_Factory::db()->ratesCollection();
			$res = $this->aggregatePipelines($pipelines,$coll);
			$this->ratesCache = $this->toKeyHashedArray($res, '_id');
			Billrun_Factory::log("Finished preparing rates cache", Zend_Log::DEBUG);
		}

		$localRates = $this->overrideEntityValues($this->ratesCache,@$account['overrides'],'rate');
		return $this->overrideEntityValues($localRates,@$subscriber['overrides'],'rate');
	}

	public function getDiscounts($account=null, $subscriber=null) {
		if(empty($this->discountsCache)) {
			$pipelines[] = $this->aggregationLogic->getCycleDateMatchPipeline($this->getCycle());
			$coll = Billrun_Factory::db()->discountsCollection();
			$res = $this->aggregatePipelines($pipelines,$coll);
			$this->discountsCache = $this->toKeyHashedArray($res, '_id');
		}

		$localDiscounts = $this->overrideEntityValues($this->discountsCache,@$account['overrides'],'discount');
		return $this->overrideEntityValues($localDiscounts,@$subscriber['overrides'],'discount');;
	}
        
        public function &getCharges() {
		if(empty($this->chargesCache)) {
			$pipelines[] = $this->aggregationLogic->getCycleDateMatchPipeline($this->getCycle());
			$coll = Billrun_Factory::db()->chargesCollection();
			$res = $this->aggregatePipelines($pipelines,$coll);
			$this->chargesCache = $this->toKeyHashedArray($res, '_id');
		}
		return $this->chargesCache;
	}

	public static function removeBeforeAggregate($billrunKey, $aids = array(), $override = true) {
		$linesColl = Billrun_Factory::db()->linesCollection();
		$billrunColl = Billrun_Factory::db()->billrunCollection();
		$billrunQuery = array('billrun_key' => $billrunKey);
		if ($aids) {
			$billrunQuery['aid']['$in'] = $aids;
		}
		//if in overirde mode only protect billed account if not then protect any invoiced account
		if($override) {
			$billrunQuery['billed'] = array('$eq' => 1) ;
		}
		$billed = $billrunColl->query($billrunQuery)->cursor();
		$protectedAids = array();
		foreach ($billed as $account) {
			$protectedAids[] = $account['aid'];
		}
		if (empty($aids)) {
			$linesRemoveQuery = array('aid' => array('$nin' => $protectedAids), 'billrun' => $billrunKey,
									'source' => 'billrun',
									'$or' => array(
										array( 'type' => array('$in' => array('service', 'flat')) ),
										array('$or' => array(
																array( 'type'=>'credit','usaget'=>'discount' ),
																array( 'type'=>'credit','usaget'=>'conditional_charge' ),
																array( 'type'=>'credit','billrun_cycle_credit' => true )
															))
									));
			$billrunRemoveQuery = array('billrun_key' => $billrunKey, 'billed' => array('$ne' => 1));;
		} else {
			$aids = array_values(array_diff($aids, $protectedAids));
			$linesRemoveQuery = array(	'aid' => array('$in' => $aids),
										'billrun' => $billrunKey,
										'source' => 'billrun',
										'$or' => array(
											array( 'type' => array('$in' => array('service', 'flat')) ),
											array( '$or' => array(
													array( 'type'=>'credit','usaget'=>'discount' ),
													array( 'type'=>'credit','usaget'=>'conditional_charge' ),
													array( 'type'=>'credit','billrun_cycle_credit' => true )
												))
											));
			$billrunRemoveQuery = array('aid' => array('$in' => $aids), 'billrun_key' => $billrunKey, 'billed' => array('$ne' => 1));
		}
                $addToLogMesaage =  !empty($aids) ? " for aids " . implode(',', $aids) : null;
                Billrun_Factory::log("Removing flat and service lines" . $addToLogMesaage, Zend_Log::DEBUG);
		$linesColl->remove($linesRemoveQuery);
                Billrun_Factory::log("Removed flat and service lines" . $addToLogMesaage, Zend_Log::DEBUG);
                
                Billrun_Factory::log("Removing billrun of " . $billrunKey . $addToLogMesaage, Zend_Log::DEBUG);
		$billrunColl->remove($billrunRemoveQuery);
                Billrun_Factory::log("Removed billrun of " . $billrunKey . $addToLogMesaage, Zend_Log::DEBUG);
	}

	public function isFakeCycle() {
		return $this->fakeCycle;
	}
	
	public function isOneTime() {
		return false;
	}

	//--------------------------------------------------------------------
	/**
	 * Override  entiries  values  based on certain condtions.
	 * @param $entites A  Key Hashed object  containg the  entities tht might be  overriden
	 * @param $overrideConditions Conditions to  override specific entites (must contain the entity  key) in the  hashed  entities list.
	 * @param $entityType the entity type to override.
	 * @return An overriden entites hashed list.
	 */

	protected function &overrideEntityValues($entites, $overrideConditions, $entityType) {
		$overridenEntites = $entites;
		if(!empty($overrideConditions)) {
			foreach($overrideConditions as $overideRule) {
				$ruleKey = $overideRule['key'];
				if($overideRule['type'] == $entityType && !empty($entites[$ruleKey])  ) {
					if(	(empty($overideRule['condition']) || Billrun_Util::isConditionMet($entites[$ruleKey],$overideRule['condition'])) ) {
							$overridenEntites[$ruleKey] = new Mongodloid_Entity( array_merge(
															$entites[$ruleKey]->getRawData(),
															$overideRule['value']
														) );
					}
				}
			}
		}

		return $overridenEntites;
	}

	protected function buildBillrun($options) {
		if (isset($options['stamp']) && $options['stamp']) {
			$this->stamp = $options['stamp'];
			// TODO: Why is there a check for "isBillrunKey"??
		} else if (isset($options['aggregator']['stamp']) && (Billrun_Util::isBillrunKey($options['aggregator']['stamp']))) {
			$this->stamp = $options['aggregator']['stamp'];
		} else {
			$next_billrun_key = Billrun_Billingcycle::getBillrunKeyByTimestamp(time());
			$current_billrun_key = Billrun_Billrun::getPreviousBillrunKey($next_billrun_key);
  			$this->stamp = $current_billrun_key;
		}
		$this->billrun = new Billrun_DataTypes_Billrun($this->stamp);
	}

	protected function beforeLoad() {
		Billrun_Factory::log("Loading page " . $this->page . " of size " . $this->size, Zend_Log::INFO);
		$this->canLoad = true;
		
		Billrun_Factory::dispatcher()->trigger('beforeAggregatorLoadData', array('aggregator' => $this));
	}

	/**
	 * load the data to aggregate
	 */
	protected function loadData() {
		if(!$this->canLoad) {
			return;
		}
		$this->canLoad = false;

		$rawResults = $this->loadRawData($this->getCycle());
		$data = $rawResults['data'];
		if (empty($data)) {
			Billrun_Factory::log('No data loaded by customer aggregator', Zend_Log::DEBUG);
		}
		$accounts = $this->parseToAccounts($data, $this);
		
		return $accounts;
	}

	protected function afterLoad(&$data) {
		if (!$this->recreateInvoices && $this->isCycle){
			$this->handleInvoices($data);
		}

		Billrun_Factory::log("Account entities loaded: " . count($data), Zend_Log::INFO);

		Billrun_Factory::dispatcher()->trigger('afterAggregatorLoadData', array('aggregator' => $this, 'data' => &$data));

		if ($this->bulkAccountPreload) {
			$this->clearForAccountPreload($data);
		}
	}

	/**
	 * Map a regular array to an array the is  keyed by a given field in the array records
	 */
	protected function toKeyHashedArray($array, $indxKey) {
		$hashed = array();
		foreach ($array as $value) {
			$key = strval($value[$indxKey]);
			$translatedDates = Billrun_Utils_Mongo::convertRecordMongodloidDatetimeFields($value);
			$hashed[$key] = $translatedDates;
		}
		return $hashed;
	}

	/**
	 * Get the raw data
	 * @param Billrun_DataTypes_CycleTime $cycle
	 * @return array of raw data
	 */
	protected function loadRawData($cycle) {
		$mongoCycle = new Billrun_DataTypes_MongoCycleTime($cycle);

		$result = array();
		if (!$this->forceAccountIds) {
			$data = $this->aggregateMongo($mongoCycle, $this->page, $this->size, null, $this->invoicing_days);
			$result['data'] = $data;
			return $result;
		}

		foreach ($this->forceAccountIds as $accountId) {
			if (Billrun_Bill_Invoice::isInvoiceConfirmed($accountId, $mongoCycle->key())) {
				Billrun_Factory::log("Invoice already confirmed for aid: " . $accountId, Zend_Log::NOTICE);
				continue;
			}
			$accountIds[] = intval($accountId);
		}
		
		if (empty($accountIds)) {
			$result['data'] = array();
			return $result;
		}
		if ($this->multiDayCycleMode) {
			$data = $this->aggregateMongo($mongoCycle, $this->page, $this->size, $accountIds, $this->invoicing_days);
		} else {
		$data = $this->aggregateMongo($mongoCycle, $this->page, $this->size, $accountIds);
		}
		$result['data'] = $data;
		return $result;
	}

	/**
	 *
	 * @param type $outputArr
	 * @param Billrun_DataTypes_CycleTime $cycle
	 * @param array $plans
	 * @param array $rates
	 * @return \Billrun_Cycle_Account
	 */
	protected function parseToAccounts($outputArr) {
		$accounts = array();
		$billrunData = array(
			'billrun_key' => $this->getCycle()->key(),
			'autoload' => !empty($this->overrideMode)
			);

		foreach ($outputArr as $subscriberPlan) {
			$aid = (string)$subscriberPlan['id']['aid'];
			$type = $subscriberPlan['id']['type'];

			if ($type === 'account') {
				$accounts[$aid]['attributes'] = $this->constructAccountAttributes($subscriberPlan);
				$raw = $subscriberPlan['id'];
				foreach($this->getAggregatorConfig('account.passthrough_data',array()) as $dstField => $srcField) {
					if(is_array($srcField) && method_exists($this, $srcField['func'])) {
						$raw[$dstField] = $this->{$srcField['func']}($subscriberPlan[$srcField['value']]);
					} else if(!empty($subscriberPlan['passthrough'][$srcField])) {
						$raw[$srcField] = $subscriberPlan['passthrough'][$srcField];
					}
				}
				$raw['sid']=0;
				$accounts[$aid]['subscribers'][$raw['sid']][] = $raw;
			} else if (($type === 'subscriber')) {
				$raw = $subscriberPlan['id'];
				foreach($this->getAggregatorConfig('subscriber.passthrough_data',array()) as $dstField => $srcField) {
					if(is_array($srcField) && method_exists($this, $srcField['func'])) {
						$ret = $this->{$srcField['func']}($subscriberPlan[$srcField['value']]);
						if (!is_null($ret) || (!isset($srcField['nullable']) || $srcField['nullable'])) {
							$raw[$dstField] = $ret;
						}
					} else if(!empty($subscriberPlan['passthrough'][$srcField])) {
						$raw[$srcField] = $subscriberPlan['passthrough'][$srcField];
					}
				}
				$raw['plans'] = $subscriberPlan['plan_dates'];
				foreach($subscriberPlan['plan_dates'] as $dates) {
					$raw['from'] = min($dates['from']->sec,  Billrun_Util::getFieldVal($raw['from'],PHP_INT_MAX) );
					$raw['to'] = max($dates['to']->sec,Billrun_Util::getFieldVal($raw['to'],0) );
				}
				$accounts[$aid]['subscribers'][$raw['sid']][] = $raw;
			} else {
				Billrun_Factory::log('Recevied a record form cycle aggregate with unknown type.',Zend_Log::ERR);
			}
		}

		$accountsToRet = array();
		foreach($accounts as $aid => $accountData) {
			$accountToAdd = $this->getAccount($billrunData, $accountData, intval($aid));
			if($accountToAdd) {
				$accountsToRet[] = $accountToAdd;
			}
		}

		return $accountsToRet;
	}

	/**
	 * Returns a single cycle account instnace.
	 * If the account already exists in billrun, returns false..
	 * @param array $billrunData
	 * @param int $aid
	 * @param Billrun_DataTypes_CycleTime $cycle
	 * @param array $plans
	 * @param array $services
	 * @param array $rates
	 * @return Billrun_Cycle_Account | false
	 */
	protected function getAccount($billrunData, $accountData, $aid) {
		// Handle no subscribers.
		if(!isset($accountData['subscribers'])) {
			$accountData['subscribers'] = array();
		}


		$billrunData['aid'] = $aid;
		$billrunData['attributes'] = $accountData['attributes'];
		$billrunData['override_mode'] = $this->overrideMode;
		$invoice = new Billrun_Cycle_Account_Invoice($billrunData);

		// Check if already exists.
		if(!$this->overrideMode && $invoice->exists()) {
			Billrun_Factory::log("Billrun " . $this->getCycle()->key() . " already exists for account " . $aid, Zend_Log::ALERT);
			return false;
		}

		$accountData['invoice'] = $invoice;
		return new Billrun_Cycle_Account($accountData, $this);
	}

	/**
	 * This function constructs the account attributes for a billrun cycle account
	 * @param array $subscriberPlan - Current subscriber plan.
	 */
	protected function constructAccountAttributes($subscriberPlan) {
		$firstname = $subscriberPlan['id']['first_name'];
		$lastname = $subscriberPlan['id']['last_name'];
		$paymentDetails = 'No payment details';
		if (isset($subscriberPlan['card_token']) && !empty($token = $subscriberPlan['card_token'])) {
			$paymentDetails = Billrun_Util::getTokenToDisplay($token);
		}
		//Add basic account data
		$accountData = array(
			'firstname' => $firstname,
			'lastname' => $lastname,
			'fullname' => $firstname . ' ' . $lastname,
			'address' => $subscriberPlan['id']['address'],
			'email' => $subscriberPlan['id']['email'],
			'payment_details' => $paymentDetails,
		);

		foreach($this->getAggregatorConfig('passthrough_data',array()) as  $invoiceField => $subscriberField) {
			if(isset($subscriberPlan['passthrough'][$subscriberField]) && $subscriberField !== "invoicing_day") {
				$accountData[$invoiceField] = $subscriberPlan['passthrough'][$subscriberField];
			} else {
				$config = Billrun_Factory::config();
				if ($subscriberField == "invoicing_day" && $config->isMultiDayCycle()) {
					if (empty($subscriberPlan['passthrough'][$subscriberField]) || !in_array($subscriberPlan['passthrough'][$subscriberField], array_map('strval', range(1, 28)))) {
						$accountData[$invoiceField] = strval($config->getConfigChargingDay());
					} else {
						$accountData[$invoiceField] = $subscriberPlan['passthrough'][$subscriberField];
					}
				}
			}
		}
		return  $accountData;
	}

	/**
	 *
	 * @param type $data
	 */
	protected function handleInvoices($data) {
		$query = array('billrun_key' => $this->stamp, 'page_number' => (int)$this->page, 'page_size' => $this->size);
		$dataCount = count($data);
		$update = array('$set' => array('count' => $dataCount));
		$this->billingCycle->update($query, $update);
	}

	/**
	 *
	 * @param type $data
	 * @return type
	 */
	protected function clearForAccountPreload($data) {
		Billrun_Factory::log('loading accounts that will be needed to be preloaded...', Zend_Log::INFO);
		$dataKeys = array_keys($data);
		//$existingAccounts = array();
		foreach ($dataKeys as $key => $aid) {
			if (!$this->overrideMode && $this->billrun->exists($aid)) {
				unset($dataKeys[$key]);
			}
		}
		return $dataKeys;
	}

	protected function beforeAggregate($accounts) {
		if (!$this->fakeCycle) {
			if ($accounts) {
				$aids = array();
				foreach ($accounts as $account) {
					$aids[] = $account->getInvoice()->getAid();
				}
				$billrunKey = $this->billrun->key();

				self::removeBeforeAggregate($billrunKey, $aids, $this->overrideMode);
			}
			$accountsToPrepone = [];
			if (Billrun_Factory::config()->getConfigValue('billrun.installments.prepone_on_termination', false)) {
				$accountsToPrepone = $this->handleInstallmentsPrepone($accounts);
			}
			$additionalAccountsToPrepone = [];
			if (!empty($this->merge_credit_installments)) {
				foreach (array_keys($this->merge_credit_installments) as $aid) {
					//check which accounts need to prepone
					if (in_array($aid, $aids)) {
						if (in_array($aid, $accountsToPrepone)) {
							if (!empty(array_diff($this->merge_credit_installments[$aid], $accountsToPrepone[$aid]))) {
								$additionalAccountsToPrepone[$aid] = array_diff($this->merge_credit_installments[$aid], $accountsToPrepone[$aid]);
							}
						} else {
							$additionalAccountsToPrepone[$aid] = $this->merge_credit_installments[$aid];
						}
					}
				}
			}
			if (!empty($additionalAccountsToPrepone)) {
				$this->preponeInstallments($additionalAccountsToPrepone);
			}
		}
	}

	/**
	 * Handles the case of a future installments on a closed subscribers/accounts
	 * 
	 * @param array $accounts
	 */
	public function handleInstallmentsPrepone($accounts) {
		$cycleEndTime = $this->getCycle()->end();
		$accountsToPrepone = [];
		
		foreach ($accounts as $account) {
			$aid = $account->getInvoice()->getAid();
			$maxDeactivationTime = PHP_INT_MIN;
			$sidsToPrepone = [];
			
			foreach ($account->getRecords() as $sub) {
				$sid = $sub->getSid();
				if ($sid == 0) {
					continue;
				}
				
				$deactivationTime = $this->getDeactivationTime($sub);
				if ($deactivationTime > $maxDeactivationTime) {
					$maxDeactivationTime = $deactivationTime;
				}
				
				if ($deactivationTime <= $cycleEndTime) {
					$sidsToPrepone[] = $sid;
				}
			}
			
			if ($maxDeactivationTime <= $cycleEndTime) {
				$sidsToPrepone[] = 0;
			}
			
			if (!empty($sidsToPrepone)) {
				$accountsToPrepone[$aid] = $sidsToPrepone;
			}
		}
		
		if (!empty($accountsToPrepone)) {
			return $this->preponeInstallments($accountsToPrepone, $this->getCycle()->key(), $this->fakeCycle);
		}
		return $accountsToPrepone;
	}
	
	/**
	 * Prepone future installments (update their billrun key to the current one)
	 * 
	 * @param array $accounts - AID as key, array of SID's as values
	 */
	public static function preponeInstallments($accounts, $billrun_key = null, $fakeCycle = false) {
		if (empty($accounts)) {
			return;
		}
		
		if(is_null($billrun_key)){
			$billrun_key = Billrun_Billingcycle::getBillrunKeyByTimestamp(time());
		}
		$query = [
			'usaget' => 'charge',
			'type' => 'credit',
			'billrun' => [
				'$gt' => $billrun_key,
				'$regex' => new Mongodloid_Regex('/^\d{6}$/i'), // 6 digits length billrun keys only
			],
			'urt' => [
				'$gte' => new Mongodloid_Date(Billrun_Billingcycle::getEndTime($billrun_key)),
			],
			'installments' => [
				'$exists' => true,
			],
			'$or' => [],
		];
		
		foreach ($accounts as $aid => $sids) {
			$query['$or'][] = [
				'aid' => $aid,
				'sid' => [
					'$in' => $sids,
				],
			];
		}
	
		$hint = [
			'billrun' => 1,
			'usaget' => 1,
			'type' => 1,
		];
		
		$linesCol = Billrun_Factory::db()->linesCollection();
		$linesToUpdate = $linesCol->query($query)->cursor()->hint($hint);
		if (empty($linesToUpdate) || $linesToUpdate->count() == 0) {
			return;
		}
		
		if ($fakeCycle) {
			return iterator_to_array($linesToUpdate);
		}
		
		$ids = array_map(function($line) {
			return $line->getId()->getMongoID();
		}, iterator_to_array($linesToUpdate));
		
		$updateQuery = [
			'_id' => [
				'$in' => array_values($ids),
			],
		];

		$update = [
			'$set' => [
				'billrun' => $billrun_key,
				'preponed' => new Mongodloid_Date(),
			],
		];
		
		$options = [
			'multiple' => true,
		];
		
		try {
			$res = $linesCol->update($updateQuery, $update, $options);
			if ($res['ok']) {
				Billrun_Factory::log($res['nModified'] . " future installments were updated for account " . $aid . ", subscribers " . implode(',', $sids) . " to the current billrun " . $billrun_key, Zend_Log::NOTICE);
			} else {
				Billrun_Factory::log("Problem updating future installments for subscribers " . implode(',', $sids) . " for billrun " . $billrun_key
				. ". error message: " . $res['err'] . ". error code: " . $res['errmsg'], Zend_log::ALERT);
			}
		} catch (Exception $e) {
			Billrun_Factory::log("Problem updating installment credit for subscribers " . implode(',', $sids) . " for billrun " . $billrun_key
				. ". error message: " . $e->getMessage() . ". error code: " . $e->getCode(), Zend_log::ALERT);
		}
	}
	
	/**
	 * Get subscriber's deactivation time
	 * 
	 * @param array $sub
	 * @return unixtimestamp
	 */
	protected function getDeactivationTime($sub) {
		return max(array_column($sub->getRecords()['plans'], 'end'));
	}


	protected function aggregatedEntity($aggregatedResults, $aggregatedEntity) {
			Billrun_Factory::dispatcher()->trigger('beforeAggregateAccount', array($aggregatedEntity));
			$externalCharges = [];
			if(!$this->isFakeCycle()) {
				Billrun_Factory::log('Finalizing the invoice', Zend_Log::DEBUG);
				Billrun_Factory::log('Writing the invoice data to DB for AID : '.$aggregatedEntity->getInvoice()->getAid());
				$aggregatedEntity->finalizeInvoice( $aggregatedResults );
				Billrun_Factory::log('Get external charges / credits from plugins', Zend_Log::DEBUG);
				Billrun_Factory::dispatcher()->trigger('beforeAggregateAccountSaveLines', array(&$aggregatedEntity, &$externalCharges, $aggregatedResults, $aggregatedEntity->getAppliedDiscounts()));
				//Save Account services / plans
				Billrun_Factory::log('Save Account services / plans', Zend_Log::DEBUG);
				$this->saveLines($aggregatedResults);
				//Save Account discounts.
				Billrun_Factory::log('Save Account discounts.', Zend_Log::DEBUG);
				$this->saveLines($aggregatedEntity->getAppliedDiscounts());
				//Save external charges provided by the plugin
				Billrun_Factory::log('Save Account external charges', Zend_Log::DEBUG);
				foreach($externalCharges as &$externalCharge) {
					$externalCharge['billrun'] = $this->getCycle()->key();
					$externalCharge['source'] = 'billrun';
					$externalCharge['billrun_cycle_credit'] = true;
					$sub = $aggregatedEntity->getSubscriber($externalCharge['sid']);
					if(!empty($sub)) {
						$sub->getInvoice()->addLines([$externalCharge]);
					} else {
						Billrun_Factory::log("Cloud not  find subscriber for external charge with stamp {$externalCharge['stamp']}, check the plugin logic!",Zend_Log::ERR);
					}
				}
				$this->saveLines($externalCharges);
				// Close the invoice (no changes to subscribers allowed )
				$aggregatedEntity->closeInvoice($this->min_invoice_id );
				//Save configurable/aggretaion data
				$aggregatedEntity->addConfigurableData();
				//Save the billrun document
				Billrun_Factory::log('Save the billrun document', Zend_Log::DEBUG);
				$aggregatedEntity->save();
			} else {
				Billrun_Factory::log('Faking finalization of the invoice', Zend_Log::DEBUG);
				$aggregatedEntity->finalizeInvoice( $aggregatedResults );
				Billrun_Factory::log('Get external charges / credits from plugins', Zend_Log::DEBUG);
				Billrun_Factory::dispatcher()->trigger('beforeAggregateAccountSaveLines', array(&$aggregatedEntity, &$externalCharges, $aggregatedResults, $aggregatedEntity->getAppliedDiscounts()));
				// Close the invoice (no changes to subscribers allowed )
				$aggregatedEntity->closeInvoice( 0 , $this->isFakeCycle());
				//Save configurable/aggretaion data
				$aggregatedEntity->addConfigurableData();
			}
			if(!empty($aggregatedResults)){
						array_push($this->successfulAccounts, $aggregatedEntity->getInvoice()->getAid());
			}
			Billrun_Factory::dispatcher()->trigger('afterAggregateAccount', array($aggregatedEntity, $aggregatedResults, $this));
			return $aggregatedResults;
	}

	protected function afterAggregate($results) {
		$end_msg = "Finished iterating page {$this->page} of size {$this->size}. Memory usage is " . round(memory_get_usage() / 1048576, 1) . " MB. Host:" . Billrun_Util::getHostName() . ". Processed " . (count($this->successfulAccounts)) . " accounts";
		Billrun_Factory::log($end_msg, Zend_Log::INFO);
		$this->sendEndMail($end_msg);

		if (!$this->recreateInvoices && $this->isCycle){
			$cycleQuery = array('billrun_key' => $this->stamp, 'page_number' => $this->page, 'page_size' => $this->size);
			$cycleUpdate = array('$set' => array('end_time' => new Mongodloid_Date()));
			$this->billingCycle->update($cycleQuery, $cycleUpdate);
		}
		if(Billrun_Billingcycle::hasCycleEnded($this->getCycle()->key(), $this->size)) {
			Billrun_Factory::dispatcher()->trigger('afterCycleDone', array($this->data, $this->getCycle(), &$this));
		}
		return $results;
	}

	protected function sendEndMail($msg) {
		$recipients = Billrun_Factory::config()->getConfigValue('log.email.writerParams.to');
		$sendMailConfig = $this->getAggregatorConfig('sendendmail', true);
		if ($recipients && $sendMailConfig) {
			Billrun_Util::sendMail("BillRun customer aggregator page finished", $msg, $recipients);
		}
	}

	/**
	 * Aggregate mongo with a query
	 * @param Billrun_DataTypes_MongoCycleTime $cycle - Current cycle time
	 * @param int $page - page
	 * @param int $size - size
	 * @param int $aids - Account ids, null by deafault
	 * @return array
	 */
	protected function aggregateMongo($cycle, $page, $size, $aids = null, $invoicing_days = null) {
                $result = $this->aggregationLogic->getCustomerAggregationForPage($cycle, $page, $size, $aids, $invoicing_days);
                if(isset($result['options'])){
                    $this->options = $result['options'];
                }
                if(isset($result['options']['merge_credit_installments'])){
                    $this->merge_credit_installments = $result['options']['merge_credit_installments'];
                }
		return $result['data'];
	}

	protected function aggregatePipelines(array $pipelines, Mongodloid_Collection $collection) {
		$cursor = $collection->aggregateWithOptions($pipelines,['allowDiskUse'=> true]);
		$results = iterator_to_array($cursor);
		if (!is_array($results) || empty($results) ||
			(isset($results['success']) && ($results['success'] === FALSE))) {
			return array();
		}
		return $results;
	}

	protected function saveLines($results) {
		if(empty($results)) {
			Billrun_Factory::log("Empty aggregate customer results, skipping save");
			return;
		}
		$linesCol = Billrun_Factory::db()->linesCollection();
		try {
			$linesCol->batchInsert($results);
		} catch (Exception $e) {
			Billrun_Factory::log($e->getMessage(), Zend_Log::ALERT);
			foreach ($results as $line) {
				try {
					$linesCol->insert($line);
				} catch (Exception $ex) {
					Billrun_Factory::log($ex->getMessage(), Zend_Log::ALERT);
				}
			}
		}
	}
	
	/**
	 * Finding which page is next in the biiling cycle
	 * @param the number of max tries to get the next page in the billing cycle
	 * @return number of the next page that should be taken
	 */
	protected function getPage($retries = 100) {

		$zeroPages = $this->getAggregatorConfig('zero_pages_limit', 2);
		if (Billrun_Billingcycle::isBillingCycleOver($this->billingCycle, $this->stamp, $this->size, $zeroPages) === TRUE){
			return false;
		}
		$pagerConfiguration = array(
			'maxProcesses' => $this->getAggregatorConfig('processes_per_host_limit', 10),
			'size' => $this->size,
			'identifingQuery' => array('billrun_key' => $this->stamp),

		);
		if (!empty($this->invoicing_days)) {
			$pagerConfiguration = array_merge($pagerConfiguration, ['invoicing_day' => current($this->invoicing_days)]);
		}
		$pager = new Billrun_Cycle_Paging( $pagerConfiguration, $this->billingCycle );

		return $pager->getPage($zeroPages, $retries);
	}

	protected function shouldRunAggregate($stamp) {
		$config = Billrun_Factory::config();
		if ($this->multiDayCycleMode && !empty($this->invoicing_days) && !$this->isFakeCycle() && !$this->allowPrematureRun) {
			for($i = 0; $i < count($this->invoicing_days); $i++) {
				if (time() < Billrun_Billingcycle::getEndTime($stamp, $this->invoicing_days[$i])) {
			return false;
		}
			}
		return true;
		} else {
			if (!$this->isFakeCycle() && !$this->allowPrematureRun && time() < Billrun_Billingcycle::getEndTime($stamp)) {
				return false;
			}
			return true;
		}
	}
	

	protected function getPlanNextTeirDate($planDates) {
		$currentTime = new Mongodloid_Date($this->getCycle()->end());
		foreach($planDates as  $planData) {
			if($planData['to'] < $currentTime) {
				continue;
			}

			$plan = new Billrun_Plan(array('name'=>$planData['plan'],'time' => $currentTime->sec));
			$nextTeirDate = $plan->getNextTierDate($planData['plan_activation']->sec, $currentTime->sec);
			return  $nextTeirDate ? new Mongodloid_Date($nextTeirDate) : NULL;
		}
		return NULL;
	}
	
	protected function getActivePlan($planDates) {
		$currentTime = new Mongodloid_Date($this->getCycle()->end());
		foreach($planDates as  $planData) {
			if($planData['to'] < $currentTime) {
				continue;
			}
			return  $planData['plan'];
		}
		return NULL;
	}
	
	protected function getPlay($play) {
		return Billrun_Utils_Plays::isPlaysInUse() ? $play : null;
	}

	public function getGeneratePdf() {
		return $this->generatePdf;
	}
	
	/**
	 * method to get aggregator configuration variable, and if not find search in parent configuration
	 * 
	 * @param string $var configuration variable
	 * @param mixed  $defaultValue default value if variable not set (in both layers
	 */
	protected function getAggregatorConfig($var, $defaultValue) {
		// there is no parent -> return variable without checking parent
		if (get_class($this) == 'Billrun_Aggregator_Customer') {
			return $this->enrichConfig($var,Billrun_Factory::config()->getConfigValue(self::$type . '.aggregator.' . $var, $defaultValue));
		}
		$retDefaultVal = Billrun_Factory::config()->getConfigValue(self::$type . '.aggregator.' . $var, $defaultValue);
		$ret = Billrun_Factory::config()->getConfigValue(static::$type . '.aggregator.' . $var, $retDefaultVal);
		return $this->enrichConfig($var,$ret);
	}

	/**
	 * Add unrelated fields/values to the  requested config
	 */
	protected function enrichConfig($var,$ret) {
		if(!is_array($ret)) {
			return $ret;
		}
		$enrichmentMapping = Billrun_Factory::config()->getConfigValue('customer.aggregator.config_enrichment', [
			'passthrough_data' => [
				'subscribers.subscriber.fields' => 'field_name',
				'subscribers.account.fields' => 'field_name'
			],
			'account.passthrough_data' => [
				'subscribers.account.fields' => 'field_name'
			],
			'subscriber.passthrough_data' => [
				'subscribers.subscriber.fields' => 'field_name',
			]
		]);
		$enrichment = [];
		if (!empty($enrichmentMapping[$var])) {
			foreach ($enrichmentMapping[$var] as $enrichKey => $enrichField) {
				foreach (Billrun_Factory::config()->getConfigValue($enrichKey, []) as $fieldDesc) {
					if ((strpos($fieldDesc[$enrichField], ".") !== false) || !isset($enrichment[current(explode(".", $fieldDesc[$enrichField]))])) {
						$enrichment[$fieldDesc[$enrichField]] = $fieldDesc[$enrichField];
					}
				}
			}
		}
		return array_merge($enrichment,$ret);
	}

	public function getData() {
		return $this->data;
	}
	
	protected function getInvoicingDays($options) {
		if (!empty($options['invoicing_days'])) {
			return !is_array($options['invoicing_days']) ? [$options['invoicing_days']] : $options['invoicing_days'];
		}else {
			return array_map('strval', $this->allowPrematureRun ? range(1, 28) : range(1, date("d", strtotime("yesterday"))));
		}
	}

}
