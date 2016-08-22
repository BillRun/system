<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
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
	protected $time;

	/**
	 * Plans the subscriber had this month
	 * @var array
	 */
	protected $plans = array();

	/**
	 * The active plan at the start of the next billing cycle
	 * @var Billrun_Plan
	 */
	protected $nextPlan = null;

	/**
	 * If the subscriber has a next plan, this is its first activation date
	 * @var string
	 */
	protected $nextPlanActivation = null;

	public function __construct($options = array()) {
		parent::__construct($options);
		if (isset($options['availableFields'])) {
			$this->availableFields = $options['availableFields'];
		}
		if (isset($options['extra_data'])) {
			$this->customerExtraData = $options['extra_data'];
		}
		if (isset($options['data'])) {
			$this->data = $options['data'];
		}
		$dataOptions = Billrun_Util::getFieldVal($options['data'], array());
		$this->constructPlans($dataOptions);
		if (isset($options['time'])) {
			$this->time = $options['time'];
		}
		if (isset($dataOptions['next_plan'])) {
			$params = array(
				'name' => $dataOptions['next_plan'],
				'time' => Billrun_Billrun::getStartTime(Billrun_Util::getFollowingBillrunKey(Billrun_Util::getBillrunKey($this->time))),
			);
			$this->nextPlan = new Billrun_Plan($params);
			$this->nextPlanActivation = $dataOptions['next_plan_activation'];
		}
	}

	protected function constructPlans($dataOptions) {
		if (!isset($dataOptions['plans']) || empty($dataOptions['plans'])) {
			$this->plans = array();
			return;
		}
		
		$plans = array();
		$planOptions = array('deactivation' => array());
		foreach ($dataOptions['plans'] as &$planArr) {
			foreach ($planArr['active_dates'] as $activeRange) {
				$planOptions['name'] = $planArr['name'];
				$planOptions['time'] =  strtotime($activeRange['from']);
				$planOptions['activation'] =  $activeRange['plan_activation'];
				if(isset($activeRange['plan_deactivation'])) {
					$planOptions['deactivation'] =  $activeRange['plan_deactivation'];
				}
				$plans[] = array_merge($activeRange, array('plan' => new Billrun_Plan($planOptions)));
			}
		}
		$this->plans = $plans;
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
		} else if (array_key_exists($name, $this->customerExtraData) && isset($this->data['extra_data'][$name])) {
			return $this->data['extra_data'][$name];
		}
		return null;
	}

	/**
	 * Return true if the subscriber has no data.
	 */
	public function isEmpty() {
		return empty($this->data);
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
		// TODO: Create a getPlan function.
		return Billrun_Factory::balance()->load($this->data['sid'], Billrun_Util::getNextChargeKey(time()));
	}

	/**
	 * get the (paged) current account(s) plans by time
	 */
	abstract public function getList($startTime, $endTime, $page, $size, $aid = null);

	/**
	 * get the list of active subscribers from a json file. Parse subscribers plans at the given time (unix timestamp)
	 */
	abstract public function getListFromFile($file_path, $time);

	abstract public function getSubscribersByParams($params, $availableFields);

	abstract public function getCredits($billrun_key, $retEntity = false);

	abstract public function getServices($billrun_key, $retEntity = false);

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

	public function getId() {
		return $this->sid;
	}

	public function getNextPlanName() {
		return $this->nextPlan;
	}

	public function getNextPlanActivationDate() {
		return $this->nextPlanActivation;
	}

	public function getCurrentPlans() {
		return $this->plans;
	}

	/**
	 * 
	 * @param string $billrunKey
	 * @return array
	 */
	public function getFlatEntries($billrunKey) {
		$startTime = Billrun_Billrun::getStartTime($billrunKey);
		$endTime = Billrun_Billrun::getEndTime($billrunKey);
		$flatEntries = array();
		foreach ($this->getCurrentPlans() as $planArray) {
			$this->mergeFlatEntries($flatEntries, $startTime, $endTime, $billrunKey, $planArray);
		}
		
		$this->handlePlanFlatEntries($flatEntries, $billrunKey, $endTime);
		return $flatEntries;
	}

	/**
	 * Merge the current step of flat entries
	 * @param type $flatEntries
	 * @param type $startTime
	 * @param type $endTime
	 * @param type $billrunKey
	 * @param type $planArray
	 */
	protected function mergeFlatEntries(&$flatEntries, $startTime, $endTime, $billrunKey, $planArray) {
		/* @var $plan Billrun_Plan */
		$plan = $planArray['plan'];
		$fromDate = $planArray['from'];
		$toDate = $planArray['to'];
		$withChargeEntries = array_merge($flatEntries, $this->getChargeFlatEntries($billrunKey, $plan, $startTime, $endTime, $fromDate, $toDate));
		$withRefundEntries = array_merge($withChargeEntries, $this->getRefundFlatEntries($billrunKey, $plan, $startTime, $endTime, $fromDate, $toDate));
		$flatEntries = $withRefundEntries;
	}
	
	protected function handlePlanFlatEntries(&$flatEntries, $billrunKey, $endTime) {
		$nextPlan = $this->getNextPlan();
		$nextPlanActivation = $this->getNextPlanActivationDate();
		if ($nextPlan && $nextPlan->isUpfrontPayment() && (date(Billrun_Base::base_dateformat, $endTime) == $nextPlanActivation)) {
			$charge = $nextPlan->getPrice(date(Billrun_Base::base_dateformat, $this->time), $nextPlanActivation, $nextPlanActivation);
			$flatEntries[] = $this->getFlatEntry($billrunKey, $nextPlan, $nextPlan['from'], $charge);
		}
	}
	
	/**
	 * Get flat entries for a charge.
	 * @param type $billrunKey
	 * @param type $plan
	 * @param type $billingStart
	 * @param type $billingEnd
	 * @param type $fromDate
	 * @param type $toDate
	 * @param type $planActivation
	 * @param type $planDeactivation
	 * @return type
	 */
	protected function getChargeFlatEntries($billrunKey, $plan, $billingStart, $billingEnd, $fromDate, $toDate) {
		$charge = null;
		if ($plan->isUpfrontPayment()) {
			$charge = $this->getChargeFlatEntriesForUpfrontPayment($billrunKey, $plan, $billingStart, $billingEnd, $fromDate, $toDate);
		} else if ($plan->getPeriodicity() == 'month') {
			$charge = $plan->getPrice($fromDate, $toDate);
		} 
		
		if($charge == null) {
			return array();
		}	
		return array($this->getFlatEntry($billrunKey, $plan, $fromDate, $charge));
	}
	
	/**
	 * Handle the monthly fraction of a charge flat when plan is to be paid upfront.
	 * @param type $billrunKey
	 * @param type $planPeriodicity
	 * @param type $billingStart
	 * @param type $billingEnd
	 * @param type $fromDate
	 * @param type $planActivation
	 * @param type $planDeactivation
	 * @return type
	 */
	protected function handleMonthlyFractionOnChargeFlatEntriesForUpfrontPay($billrunKey, $planPeriodicity, $billingStart, $billingEnd, $fromDate, $planActivation, $planDeactivation) {
		$monthsEnd = $planDeactivation;
		if (empty($planDeactivation)) {
			$monthsEnd = date(Billrun_Base::base_dateformat, $billingEnd - 1);
		} 
		$monthsDiff = Billrun_Plan::getMonthsDiff($planActivation, $monthsEnd);
		return $this->getMonthlyFractionOnChargeFlatEntriesForUpfrontPay($billrunKey, $planPeriodicity, $billingStart, $fromDate, $planActivation, $planDeactivation, $monthsDiff);
	}
	
	/**
	 * Get the charge flat entries when payment is upfront
	 * @param type $billrunKey
	 * @param Billrun_Plan $plan
	 * @param type $billingStart
	 * @param type $billingEnd
	 * @param type $fromDate
	 * @param type $toDate
	 * @param type $planActivation
	 * @param type $planDeactivation
	 * @return integer charge or null on failure.
	 */
	protected function getChargeFlatEntriesForUpfrontPayment($billrunKey, $plan, $billingStart, $billingEnd, $fromDate, $toDate) {
		$monthlyFraction = $this->handleMonthlyFractionOnChargeFlatEntriesForUpfrontPay($billrunKey, $plan->getName(), $billingStart, $billingEnd, $fromDate, $plan->getActivation(), $plan->getDectivation());
		if ($monthlyFraction == null) {
			return null;
		}
		return $monthlyFraction * $plan->getPrice($fromDate, $toDate);
	}
	
	/**
	 * Get the monthly fraction for caculating the charge for a flat entry
	 * @param type $billrunKey
	 * @param type $planPeriodicity
	 * @param type $billingStart
	 * @param type $fromDate
	 * @param type $planActivation
	 * @param type $planDeactivation
	 * @param type $monthsDiff
	 * @return int
	 */
	protected function getMonthlyFractionOnChargeFlatEntriesForUpfrontPay($billrunKey, $planPeriodicity, $billingStart, $fromDate, $planActivation, $planDeactivation, $monthsDiff) {
		if (empty($planDeactivation)) {
			// TODO: What does this condition checks?
			if ($planPeriodicity == 'month' || ($planPeriodicity == 'year' && (((floor($monthsDiff) % 12) + $monthsDiff - floor($monthsDiff)) <= 1))) {
				return 1;
			}
		}
		
		// subscriber deactivates and should be charged for a partial month
		if (strtotime($planActivation) > $billingStart) { 
			return Billrun_Plan::calcFractionOfMonth($billrunKey, $planActivation, $planDeactivation) / ($planPeriodicity == 'year' ? 12 : 1);
		}
		
		$planDatesDiff = $monthsDiff;
		if (floor(Billrun_Plan::getMonthsDiff($planActivation, $fromDate)) != floor($planDatesDiff)) {
			// TODO: What does this function checks?
			if ($planPeriodicity == 'year' && (((floor($monthsDiff) % 12) + $monthsDiff - floor($monthsDiff)) <= 1)) {
				return ((floor($monthsDiff) % 12) + $monthsDiff - floor($monthsDiff)) / 12;
			} 
			if ($planPeriodicity == 'month') {
				return $planDatesDiff - floor($planDatesDiff);
			}
		}
		return null;
	}
	
	/**
	 * Get the flat entries to be refunded
	 * @param type $billrunKey
	 * @param type $plan
	 * @param type $billingStart
	 * @param type $billingEnd
	 * @param type $fromDate
	 * @param type $toDate
	 * @param type $planActivation
	 * @param type $planDeactivation
	 * @return type
	 */
	protected function getRefundFlatEntries($billrunKey, $plan, $billingStart, $billingEnd, $fromDate, $toDate) {
		$charge = null;
		if ($plan->isUpfrontPayment()) {
			$charge = $this->getChargeRefundFlatEntriesForUpfrontPayment($billrunKey, $plan, $billingStart, $fromDate, $toDate);
		}
		if ($charge == null) {
			return array();
		}
		return array($this->getFlatEntry($billrunKey, $plan, $fromDate, $charge));
	}

	/**
	 * 
	 * @param type $billrunKey
	 * @param Billrun_Plan $plan
	 * @param type $billingStart
	 * @param type $fromDate
	 * @param type $toDate
	 * @return type
	 */
	protected function getChargeRefundFlatEntriesForUpfrontPayment($billrunKey, $plan, $billingStart, $fromDate, $toDate) {
		$planDeactivation = $plan->getDeactivation();
		if (empty($planDeactivation)) {
			return null;
		}
		
		$planActivation = $plan->getActivation();
		// get a refund for a cancelled plan paid upfront
		if (strtotime($planActivation) > $billingStart) { 
			return null;
		}
		$lastUpfrontCharge = $plan->getPrice($fromDate, $toDate);
		if ($plan->getPeriodicity() == 'year') {
			$monthsDiff = Billrun_Plan::getMonthsDiff($planActivation, $planDeactivation);
			$refundFraction = 1 - ((floor($monthsDiff) % 12) + $monthsDiff - floor($monthsDiff));
		} else if ($plan->getPeriodicity() == 'month') {
			$refundFraction = 1 - Billrun_Plan::calcFractionOfMonth($billrunKey, $fromDate, $planDeactivation);
		} else {
			Billrun_Factory::log("Cannot handle refund flat entries for periodicity that is not month or year");
			return null;
		}
		return -$lastUpfrontCharge * $refundFraction;
	}

	protected function getFlatEntry($billrunKey, $plan, $start, $charge) {
		$startTimestamp = strtotime($start);
		$flatEntry = new Mongodloid_Entity(array(
			'aid' => $this->aid,
			'sid' => $this->sid,
			'source' => 'billrun',
			'billrun' => $billrunKey,
			'type' => 'flat',
			'usaget' => 'flat',
			'urt' => new MongoDate($startTimestamp),
			'aprice' => $charge,
			'plan' => $plan->getName(),
			'plan_ref' => $plan->createRef(),
			'process_time' => new MongoDate(),
		));
		$stamp = md5($this->aid . '_' . $this->sid . $plan->getName() . '_' . $start . $billrunKey);
		$flatEntry['stamp'] = $stamp;
		return $flatEntry;
	}

	/**
	 * 
	 * @return Billrun_Plan
	 */
	public function getNextPlan() {
		return $this->nextPlan;
	}

}
