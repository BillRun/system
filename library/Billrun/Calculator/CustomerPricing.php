<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator for  pricing  billing lines with customer price.
 *
 * @package  calculator
 * @since    0.5
 */
class Billrun_Calculator_CustomerPricing extends Billrun_Calculator {

	const DEF_CALC_DB_FIELD = 'aprice';

	public $pricingField = self::DEF_CALC_DB_FIELD;
	public $interconnectChargableFlagField = 'interconnect_chargable';
	public $interconnectChargeField = 'interconnect_aprice';
	static protected $type = "pricing";
	static protected $precision = 0.00001;

	/**
	 *
	 * @var boolean is customer price vatable by default
	 */
	protected $vatable = true;

	/**
	 * Save unlimited usages to balances
	 * @var boolean
	 */
	protected $unlimited_to_balances = true;
	protected $plans = array();

	/**
	 *
	 * @var Mongodloid_Collection 
	 */
	protected $balances = null;

	/**
	 *
	 * @var int timestamp
	 */
	protected $billrun_lower_bound_timestamp;

	/**
	 * Minimum possible billrun key for newly calculated lines
	 * @var string 
	 */
	protected $active_billrun;

	/**
	 * End time of the active billrun (unix timestamp)
	 * @var int
	 */
	protected $active_billrun_end_time;

	/**
	 * Second minimum possible billrun key for newly calculated lines
	 * @var string
	 */
	protected $next_active_billrun;

	/**
	 * inspect loops in updateSubscriberBalance
	 * @see mongodb update where value equale old value
	 * 
	 * @var int
	 */
	protected $countConcurrentRetries;

	/**
	 * max retries on concurrent balance updates loops
	 * 
	 * @var int
	 */
	protected $concurrentMaxRetries;

	/**
	 * Array of subscriber ids queued for rebalance in rebalance_queue collection
	 * @var array
	 */
	protected $sidsQueuedForRebalance;

	/**
	 * balance that customer pricing update
	 * 
	 * @param Billrun_Balance $balance
	 */
	protected $balance;
	
	/**
	 * prepaid minimum balance volume
	 * 
	 * @var float
	 */
	protected $min_balance_volume = null;
	
	/**
	 * prepaid minimum balance cost
	 * 
	 * @var float
	 */
	protected $min_balance_cost = null;


	/**
	 * call offset
	 * 
	 * @param int $call_offset
	 */
	protected $call_offset = 0;

	public function __construct($options = array()) {
		if (isset($options['autoload'])) {
			$autoload = $options['autoload'];
		} else {
			$autoload = true;
		}

		if (isset($options['realtime'])) {
			$realtime = $options['realtime'];
		} else {
			$realtime = false;
		}

		$options['autoload'] = false;
		parent::__construct($options);

		if (isset($options['calculator']['limit'])) {
			$this->limit = $options['calculator']['limit'];
		}
		if (isset($options['calculator']['vatable'])) {
			$this->vatable = $options['calculator']['vatable'];
		}
		if (isset($options['calculator']['months_limit'])) {
			$this->months_limit = $options['calculator']['months_limit'];
		}
		if (isset($options['calculator']['unlimited_to_balances'])) {
			$this->unlimited_to_balances = (boolean) ($options['calculator']['unlimited_to_balances']);
		}
		$this->billrun_lower_bound_timestamp = is_null($this->months_limit) ? 0 : strtotime($this->months_limit . " months ago");
		// set months limit
		if ($autoload) {
			$this->load();
		}
		//TODO: check how to remove call to loadRates
		$this->balances = Billrun_Factory::db()->balancesCollection()->setReadPreference('RP_PRIMARY');
		if (!$realtime) {
			$this->loadRates();
			$this->loadPlans();
			$this->active_billrun = Billrun_Billrun::getActiveBillrun();
			$this->active_billrun_end_time = Billrun_Util::getEndTime($this->active_billrun);
			$this->next_active_billrun = Billrun_Util::getFollowingBillrunKey($this->active_billrun);
		}
		// max recursive retrues for value=oldValue tactic
		$this->concurrentMaxRetries = (int) Billrun_Factory::config()->getConfigValue('updateValueEqualOldValueMaxRetries', 8);
		$this->sidsQueuedForRebalance = array_flip(Billrun_Factory::db()->rebalance_queueCollection()->distinct('sid'));
	}

	protected function getLines() {
		$query = array();
		$query['type'] = array('$in' => array('ggsn', 'smpp', 'mmsc', 'smsc', 'nsn', 'tap3', 'credit', 'nrtrde'));
		return $this->getQueuedLines($query);
	}

	public function setCallOffset($val) {
		$this->call_offset = $val;
	}

	public function getCallOffset() {
		return $this->call_offset;
	}

	/**
	 * execute the calculation process
	 * @TODO this function mighh  be a duplicate of  @see Billrun_Calculator::calc() do we really  need the diffrence  between Rate/Pricing? (they differ in the plugins triggered)
	 */
	public function calc() {
		Billrun_Factory::dispatcher()->trigger('beforePricingData', array('data' => $this->data));
		$lines_coll = Billrun_Factory::db()->linesCollection();

		$lines = $this->pullLines($this->lines);
		foreach ($lines as $key => $line) {
			if ($line) {
				Billrun_Factory::dispatcher()->trigger('beforePricingDataRow', array('data' => &$line));
				//Billrun_Factory::log("Calculating row: ".print_r($item,1),  Zend_Log::DEBUG);
				$line->collection($lines_coll);
				if ($this->isLineLegitimate($line)) {
					if ($this->updateRow($line) === FALSE) {
						unset($this->lines[$line['stamp']]);
						continue;
					}
					$this->data[$line['stamp']] = $line;
				}
				//$this->updateLinePrice($item); //@TODO  this here to prevent divergance  between the priced lines and the subscriber's balance/billrun if the process fails in the middle.
				Billrun_Factory::dispatcher()->trigger('afterPricingDataRow', array('data' => &$line));
			}
		}
		Billrun_Factory::dispatcher()->trigger('afterPricingData', array('data' => $this->data));
	}

	public function updateRow($row) {
		if (isset($this->sidsQueuedForRebalance[$row['sid']])) {
			return false;
		}
		try {
			$this->countConcurrentRetries = 0;
			Billrun_Factory::dispatcher()->trigger('beforeCalculatorUpdateRow', array(&$row, $this));
			$this->setCallOffset(isset($row['call_offset']) ? $row['call_offset'] : 0);
			$rate = $this->getRowRate($row);

			//TODO  change this to be configurable.
			$pricingData = array();

			$usage_type = $row['usaget'];

			if (isset($row['usagev']) || $row['charging_type'] == 'prepaid') {  // for prepaid, volume is by balance left over
				$volume = $row['usagev'];
				$plan_name = isset($row['plan']) ? $row['plan'] : null;
				if ($row['type'] == 'credit') {
					$charges = self::getPriceByRate($rate, $usage_type, $volume, $row['plan'], $this->getCallOffset());
					$pricingData = array($this->pricingField => $charges['total'], $this->interconnectChargeField => $charges['interconnect']);
				} else if ($row['type'] == 'service') {
					$charges = self::getPriceByRate($rate, $usage_type, $volume, $plan_name, $this->getCallOffset());
					$pricingData = array($this->pricingField => $charges['total'], $this->interconnectChargeField => $charges['interconnect']);
				} else {
					$pricingData = $this->updateSubscriberBalance($row, $usage_type, $rate);
					if ($pricingData === FALSE) {
						return ($row['charging_type'] == 'prepaid' ? TRUE : FALSE);
					}
				}

				if ($this->isBillable($rate)) {
					if (!$pricingData) {
						return false;
					}

					// billrun cannot override on api calls
					if ($row['charging_type'] != 'prepaid' && (!isset($row['billrun']) || $row['source'] != 'api')) {
						$pricingData['billrun'] = $row['urt']->sec <= $this->active_billrun_end_time ? $this->active_billrun : $this->next_active_billrun;
					}
				}
			} else {
				Billrun_Factory::log("Line with stamp " . $row['stamp'] . " is missing volume information", Zend_Log::ALERT);
				return false;
			}

			$interconnect_arate_key = self::getInterconnect($rate, $row['usaget'], $row['plan']);
			if (!empty($interconnect_arate_key)) {
				$row['interconnect_arate_key'] = $interconnect_arate_key;
			}
			
			if (isset($rate['params']['interconnect']) && $rate['params']['interconnect']) {
				$row['interconnect_arate_key'] = $rate['key'];
			}

			$pricingDataTxt = "Saving pricing data to line with stamp: " . $row['stamp'] . ".";
			foreach ($pricingData as $key => $value) {
				$pricingDataTxt .= " " . $key . ": " . $value . ".";
			}
			Billrun_Factory::log($pricingDataTxt, Zend_Log::DEBUG);
			$row->setRawData(array_merge($row->getRawData(), $pricingData));

			Billrun_Factory::dispatcher()->trigger('afterCalculatorUpdateRow', array(&$row, $this));
			return $row;
		} catch (Exception $e) {
			Billrun_Factory::log('Line with stamp ' . $row['stamp'] . ' crashed when trying to price it. got exception :' . $e->getCode() . ' : ' . $e->getMessage() . "\n trace :" . $e->getTraceAsString(), Zend_Log::ERR);
			return false;
		}
	}

	/**
	 * Gets the subscriber's balance. If it does not exist, creates it.
	 * 
	 * @param type $row
	 * 
	 * @return Billrun_Balance
	 * 
	 * @todo Add compatiblity to prepaid
	 */
	public function loadSubscriberBalance($row, $granted_volume = null, $granted_cost = null) {
		// we moved the init of plan_ref to customer calc, we leave it here only for verification and avoid b/c issues
		if (!isset($row['plan_ref'])) {
			$plan = Billrun_Factory::plan(array('name' => $row['plan'], 'time' => $row['urt']->sec, /* 'disableCache' => true */));
			$plan_ref = $plan->createRef();
			if (is_null($plan_ref)) {
				Billrun_Factory::log('No plan found for subscriber ' . $row['sid'], Zend_Log::ALERT);
				$row['usagev'] = 0;
				$row['apr'] = 0;
				return false;
			}
			$row['plan_ref'] = $plan_ref;
		}
		$instanceOptions = array_merge($row->getRawData(), array('granted_usagev' => $granted_volume, 'granted_cost' => $granted_cost));
		$balance = new Billrun_Balance($instanceOptions);
		if (!$balance || !$balance->isValid()) {
			Billrun_Factory::log("couldn't get balance for subscriber: " . $row['sid'], Zend_Log::INFO);
			$row['usagev'] = 0;
			$row['apr'] = 0;
			unset($this->balance);
			return false;
		} else {
			Billrun_Factory::log("Found balance  for subscriber " . $row['sid'], Zend_Log::DEBUG);
		}
		$this->balance = $balance;
		return true;
	}

	/**
	 * get subscriber plan object
	 * identification using the balance collection
	 * 
	 * @param array $sub_balance the subscriber balance
	 * @return type
	 */
	protected function getPlan($sub_balance) {
		$subscriber_current_plan = $this->getBalancePlan($sub_balance);
		return Billrun_Factory::plan(array('data' => $subscriber_current_plan));
	}

	/**
	 * 
	 * @return array
	 */
	protected function getFreeRowPricingData() {
		return array(
			'in_plan' => 0,
			'over_plan' => 0,
			'out_plan' => 0,
			'in_group' => 0,
			'over_group' => 0,
			$this->pricingField => 0,
			$this->interconnectChargeField => 0,
		);
	}

	/**
	 * Get pricing data for a given rate / subcriber.
	 * @param int $volume The usage volume (seconds of call, count of SMS, bytes  of data)
	 * @param string $usageType The type  of the usage (call/sms/data)
	 * @param mixed $rate The rate of associated with the usage.
	 * @param mixed $sub_balance the  subscriber that generated the usage.
	 * @param Billrun_Plan $plan the subscriber's current plan
	 * @return Array the 
	 * @todo refactoring the if-else-if-else-if-else to methods
	 */
	protected function getLinePricingData($volume, $usageType, $rate, $sub_balance, $plan, $row = null) {
		if ($this->isFreeLine($row)) {
			return $this->getFreeRowPricingData();
		}
		$ret = array();
		if ($plan->isRateInBasePlan($rate, $usageType)) {
			$planVolumeLeft = $plan->usageLeftInBasePlan($sub_balance, $rate, $usageType);
			$volumeToCharge = $volume - $planVolumeLeft;
			if ($volumeToCharge < 0) {
				$volumeToCharge = 0;
				$ret['in_plan'] = $volume;
			} else if ($volumeToCharge > 0) {
				if ($planVolumeLeft > 0) {
					$ret['in_plan'] = $volume - $volumeToCharge;
				}
				$ret['over_plan'] = $volumeToCharge;
			}
		} else if ($plan->isRateInPlanGroup($rate, $usageType)) {
			$groupVolumeLeft = $plan->usageLeftInPlanGroup($sub_balance, $rate, $usageType);
			$volumeToCharge = $volume - $groupVolumeLeft;
			if ($volumeToCharge < 0) {
				$volumeToCharge = 0;
				$ret['in_group'] = $ret['in_plan'] = $volume;
			} else if ($volumeToCharge > 0) {
				if ($groupVolumeLeft > 0) {
					$ret['in_group'] = $ret['in_plan'] = $volume - $volumeToCharge;
				}
				if ($plan->getPlanGroup() !== FALSE) { // verify that after all calculations we are in group
					$ret['over_group'] = $ret['over_plan'] = $volumeToCharge;
				} else {
					$ret['out_group'] = $ret['out_plan'] = $volumeToCharge;
				}
			}
			if ($plan->getPlanGroup() !== FALSE) {
				$ret['arategroup'] = $plan->getPlanGroup();
			}
		} else { // else if (dispatcher->chain_of_responsibilty)->isRateInPlugin {dispatcher->trigger->calc}
			$ret['out_plan'] = $volumeToCharge = $volume;
		}

		$charges = self::getChargesByRate($rate, $usageType, $volumeToCharge, $plan->getName(), $this->getCallOffset());
		Billrun_Factory::dispatcher()->trigger('afterChargesCalculation', array(&$row, &$charges));

		$ret[$this->pricingField] = $charges['total'];
		$ret[$this->interconnectChargeField] = $charges['interconnect'];
		return $ret;
	}

	/**
	 * Determines if a rate should not produce billable lines, but only counts the usage
	 * @param Mongodloid_Entity|array $rate the input rate
	 * @return boolean
	 */
	public function isBillable($rate) {
		return !isset($rate['billable']) || $rate['billable'] === TRUE;
	}

	/**
	 * Override parent calculator to save changes with update (not save)
	 */
	public function writeLine($line, $dataKey) {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteLine', array('data' => $line, 'calculator' => $this));
		$save = array();
		$saveProperties = $this->getPossiblyUpdatedFields();
		foreach ($saveProperties as $p) {
			if (!is_null($val = $line->get($p, true))) {
				$save['$set'][$p] = $val;
			}
		}
		$where = array('stamp' => $line['stamp']);
		if ($save) {
			Billrun_Factory::db()->linesCollection()->update($where, $save);
		}
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteLine', array('data' => $line, 'calculator' => $this));
		if (!isset($line['usagev']) || $line['usagev'] === 0) {
			$this->removeLineFromQueue($line);
			unset($this->data[$dataKey]);
		}
	}

	public function getPossiblyUpdatedFields() {
		return array($this->pricingField, $this->interconnectChargeField, 'billrun', 'over_plan', 'in_plan', 'out_plan', 'plan_ref', 'usagesb', 'arategroup', 'over_arate', 'over_group', 'in_group', 'in_arate');
	}

	/**
	 * Calculates the charges for the given volume
	 * 
	 * @param array $rate the rate entry
	 * @param string $usageType the usage type
	 * @param int $volume The usage volume (seconds of call, count of SMS, bytes of data)
	 * @param object $plan The plan the line is associate to
	 * @param int $offset call start offset in seconds
	 * @param int $time start of the call (unix timestamp)
	 * @todo : changed mms behavior as soon as we will add mms to rates
	 * 
	 * @return array the calculated charges
	 */
	public static function getChargesByRate($rate, $usageType, $volume, $plan = null, $offset = 0, $time = NULL) {
		if (!empty($interconnect = self::getInterConnect($rate, $usageType, $plan))) {
			$query = array_merge(
				array(
				'key' => $interconnect,
				'params.interconnect' => TRUE,
				), Billrun_Util::getDateBoundQuery($time)
			);
			$interconnectRate = Billrun_Factory::db()->ratesCollection()->query($query)->cursor()->limit(1)->current();
			$interconnectCharge = static::getTotalChargeByRate($interconnectRate, $usageType, $volume, $plan, $offset, $time);
		} else {
			$interconnectCharge = 0;
		}
		if ($usageType == 'mms') {
			$usageType = 'sms';
		} //TODO: should be changed as soon as we will add mms to rates
		$tariff = static::getTariff($rate, $usageType, $plan);
		if ($offset) {
			$chargeWoIC = static::getChargeByVolume($tariff, $offset + $volume) - static::getChargeByVolume($tariff, $offset);
		} else {
			$chargeWoIC = static::getChargeByVolume($tariff, $volume);
		}
		if ($interconnectCharge && $interconnectRate && (!isset($interconnectRate['params']['chargable']) || $interconnectRate['params']['chargable'])) {
			$ret = array(
				'interconnect' => $interconnectCharge,
				'total' => $interconnectCharge + $chargeWoIC,
			);
		} else if (isset($rate['params']['interconnect']) && $rate['params']['interconnect'] && isset($rate['params']['chargable']) && $rate['params']['chargable']) { // the rate charge is interconnect charge
			$total = $chargeWoIC + $interconnectCharge;
			$ret = array(
				'interconnect' => $total,
				'total' => $total,
			);
		} else {
			$ret = array(
				'interconnect' => $interconnectCharge,
				'total' => $chargeWoIC,
			);
		}
		return $ret;
	}

	public static function getTotalChargeByRate($rate, $usageType, $volume, $plan = null, $offset = 0, $time = NULL) {
		return static::getChargesByRate($rate, $usageType, $volume, $plan, $offset, $time)['total'];
	}
	
	public static function getIntervalCeiling($tariff, $volume) {
		$ret = 0;
		foreach ($tariff['rate'] as $currRate) {
			if (!isset($currRate['from'])) {
				$currRate['from'] = isset($lastRate['to']) ? $lastRate['to'] : 0;
			}
			if (isset($currRate['rate'])) {
				$currRate = $currRate['rate'];
			}
			if (0 == $volume) { // volume could be negative if it's a refund amount
				break;
			}//break if no volume left to price.
			$maxVolumeInRate = $currRate['to'] - $currRate['from'];
			$volumeToPriceCurrentRating = ($volume < $maxVolumeInRate) ? $volume : $maxVolumeInRate;
			$volume -= $volumeToPriceCurrentRating;
			$ret += (ceil($volumeToPriceCurrentRating / $currRate['interval']) * $currRate['interval']);
			$lastRate = $currRate;
		}
		
		return $ret;
		
	}

	public static function getChargeByVolume($tariff, $volume) {
		$accessPrice = self::getAccessPrice($tariff);
		if ($volume < 0) {
			$volume *= (-1);
			$isNegative = true;
		} else {
			$isNegative = false;
		}
		$price = 0;
		foreach ($tariff['rate'] as $currRate) {
			if (!isset($currRate['from'])) {
				$currRate['from'] = isset($lastRate['to']) ? $lastRate['to'] : 0;
			}
			if (isset($currRate['rate'])) {
				$currRate = $currRate['rate'];
			}
			if (0 == $volume) { // volume could be negative if it's a refund amount
				break;
			}//break if no volume left to price.
			$maxVolumeInRate = $currRate['to'] - $currRate['from'];
			$volumeToPriceCurrentRating = ($volume < $maxVolumeInRate) ? $volume : $maxVolumeInRate; // get the volume that needed to be priced for the current rating
			if (isset($currRate['ceil'])) {
				$ceil = $currRate['ceil'];
			} else {
				$ceil = true;
			}
			if ($ceil) {
				$price += floatval(ceil($volumeToPriceCurrentRating / $currRate['interval']) * $currRate['price']); // actually price the usage volume by the current 	
			} else {
				$price += floatval($volumeToPriceCurrentRating / $currRate['interval'] * $currRate['price']); // actually price the usage volume by the current 
			}
			$volume = $volume - $volumeToPriceCurrentRating; //decrease the volume that was priced
			$lastRate = $currRate;
		}
		$ret = $accessPrice + $price;
		return ($isNegative ? $ret * (-1) : $ret);
	}

	/**
	 * Calculates the volume for the given price
	 * 
	 * @param array $rate the rate entry
	 * @param string $usage_type the usage type
	 * @param int $price The price
	 * @param object $plan The plan the line is associate to
	 * @param int $offset call start offset in seconds
	 * 
	 * @return int the calculated volume
	 */
	protected function getVolumeByRate($rate, $usage_type, $price, $plan = null, $offset = 0) {
		// Check if the price is enough for default usagev
		$defaultUsage = (float) Billrun_Factory::config()->getConfigValue('rates.prepaid_granted.' . $usage_type . '.usagev',  0, 'float'); // float avoid set type to int
		$defaultUsagePrice = static::getTotalChargeByRate($rate, $usage_type, $defaultUsage, $plan, $offset);
		if ($price >= $defaultUsagePrice) {
			return $defaultUsage;
		}

		$this->initMinBalanceValues($rate, $usage_type, $plan);

		// Check if the price is enough for minimum cost
		if ($price < $this->min_balance_cost) {
			return 0;
		}

		// we removed this in case we have rate tiers
//		if ($price == $this->min_balance_cost) {
//			return $this->min_balance_volume;
//		}

		// Let's find the best volume by lion in the desert algorithm
		$previousUsage = $defaultUsage;
		$currentUsage = $defaultUsage - (abs($defaultUsage - $this->min_balance_volume) / 2);
		$epsilon = Billrun_Factory::config()->getConfigValue('customerPricing.calculator.getVolumeByRate.epsilon', 0.5);
		$limitLoop = Billrun_Factory::config()->getConfigValue('customerPricing.calculator.getVolumeByRate.limitLoop', 40);
		while (abs($currentUsage - $previousUsage) > $epsilon && $limitLoop-- > 0) {
			$currentPrice = static::getTotalChargeByRate($rate, $usage_type, $currentUsage, $plan, $offset);
			$diff = abs($currentUsage - $previousUsage) / 2;
			if ($price < $currentPrice) {
				$previousUsage = $currentUsage;
				$currentUsage -= $diff;
			} else {
				$previousUsage = $currentUsage;
				$currentUsage += $diff;
			}
		}

		// Check if the price is enough for minimum cost
		if ($currentPrice >= $this->min_balance_cost) {
			return floor($currentUsage);
		}
		return 0;
	}

	public static function getTariff($rate, $usage_type, $planName = null) {
		if (!is_null($planName) && isset($rate['rates'][$usage_type][$planName])) {
			return $rate['rates'][$usage_type][$planName];
		}
		if (isset($rate['rates'][$usage_type]['BASE'])) {
			return $rate['rates'][$usage_type]['BASE'];
		}
		return $rate['rates'][$usage_type];
	}

	/**
	 * Gets correct access price from tariff
	 * @param array $tariff the tariff structure
	 * @return float Access price
	 */
	static protected function getAccessPrice($tariff) {
		if (isset($tariff['access'])) {
			return $tariff['access'];
		}
		return 0;
	}

	protected function isFreeLine(&$row) {
		if (isset($row['charging_type']) && $row['charging_type'] === 'prepaid') {
			if (isset($row['free_line'])) {
				return $row['free_line'];
			}
			$isFreeLine = false;
			Billrun_Factory::dispatcher()->trigger('isFreeLine', array(&$row, &$isFreeLine));
			$row['free_line'] = $isFreeLine;
			return $isFreeLine;
		}
		return false;
	}

	/**
	 * Update the subscriber balance for a given usage
	 * Method is recursive - it tries to update subscriber balances with value=oldValue tactic
	 * There is max retries for the recursive to run and the value is configured
	 * 
	 * @param Mongodloid_Entity $row the input line
	 * @param string $usage_type The type  of the usage (call/sms/data)
	 * @param mixed $rate The rate of associated with the usage.
	 * @param int $volume The usage volume (seconds of call, count of SMS, bytes  of data)
	 * 
	 * @return mixed array with the pricing data on success, false otherwise
	 * @todo refactoring and make it more abstract
	 * @todo create unit test for this method because it's critical method
	 * @todo add compatiblity to prepaid
	 * 
	 */
	public function updateSubscriberBalance($row, $usage_type, $rate) {
		$row['granted_return_code'] = Billrun_Factory::config()->getConfigValue('prepaid.ok');
		$plan = Billrun_Factory::plan(array('name' => $row['plan'], 'time' => $row['urt']->sec, /* 'disableCache' => true */));
		if ($row['charging_type'] === 'prepaid') {
			$this->initMinBalanceValues($rate, $row['usaget'], $plan);
		} else {
			$this->min_balance_volume = null;
			$this->min_balance_cost = null;
		}
		if (!$this->loadSubscriberBalance($row, $this->min_balance_volume, $this->min_balance_cost)) { // will load $this->balance
			if ($row['charging_type'] === 'prepaid') {
				// check first if this free call and allow it if so
				if ($this->min_balance_cost == '0') {
					if ((isset($row['api_name']) && in_array($row['api_name'], array('start_call', 'release_call'))) ||
							(isset($row['record_type']) && $row['record_type'] === 'final_request')){
						$granted_volume = 0;
					} else {
						$granted_volume = $this->getPrepaidGrantedVolumeByRate($rate, $row['usaget'], $plan->getName());
					}
					$charges = $this->getChargesByRate($rate, $row['usaget'], $granted_volume, $plan->getName(), $this->getCallOffset());
					$granted_cost = $charges['total'];
					return array(
						$this->pricingField => $granted_cost,
						$this->interconnectChargeField => $charges['interconnect'],
						'usagev' => $granted_volume,
					);
				}
				$row['granted_return_code'] = Billrun_Factory::config()->getConfigValue('prepaid.customer.no_available_balances');
			}
			Billrun_Factory::dispatcher()->trigger('afterSubscriberBalanceNotFound', array(&$row));
			if ($row['usagev'] === 0) {
				return false;
			} else if ($row['usagev'] === FALSE) { // volume is 0 but require to ignore on the last dispatcher
				$row['usagev'] = 0;
			}
		}
		
		if ($row['charging_type'] === 'postpaid') {
			$balance_totals_key = $plan->getBalanceTotalsKey($usage_type, $rate);
		} else if (!empty($this->balance)) {
			$balance_totals_key = $this->balance->getBalanceChargingTotalsKey($usage_type);
		} else {
			$balance_totals_key = $row['usaget'];
		}

		if ($row['charging_type'] === 'prepaid' && !(isset($row['prepaid_rebalance']) && $row['prepaid_rebalance'])) { // If it's a prepaid row, but not rebalance
			$row['apr'] = self::getTotalChargeByRate($rate, $row['usaget'], $row['usagev'], $row['plan'], $this->getCallOffset());
			if (!$this->balance && $this->isFreeLine($row)) {
				return $this->getFreeRowPricingData();
			}
			$row['balance_ref'] = $this->balance->createRef();
			$row['usagev'] = $volume = $this->getPrepaidGrantedVolume($row, $rate, $this->balance, $usage_type, $balance_totals_key);
                        if ((isset($row['billrun_pretend']) && $row['billrun_pretend'] && $row['usaget'] === 'data')){
                            $row['granted_usagev'] = $volume;
                            $row['usagev'] = $volume = 0;
                        }
		} else {
			$volume = $row['usagev'];
		}
		$balanceRaw = $this->balance->getRawData();
		$this->countConcurrentRetries++;
		Billrun_Factory::dispatcher()->trigger('beforeUpdateSubscriberBalance', array($this->balance, &$row, $rate, $this));
		$tx = $this->balance->get('tx');
		if (is_array($tx) && empty($tx)) {
			$this->balance->set('tx', new stdClass());
//			$this->balance->save();
			$resetTxQuery = array(
				'_id' => $this->balance->getId()->getMongoID(),
			);
			$resetTxUpdate = array(
				'$set' => array(
					'tx' => new stdClass(),
				),
			);
			$this->balances->update($resetTxQuery, $resetTxUpdate);
		}
		if (!empty($tx) && array_key_exists($row['stamp'], $tx)) { // we're after a crash
			$pricingData = $tx[$row['stamp']]; // restore the pricingData before the crash
			return $pricingData;
		}
		$pricingData = $this->getLinePricingData($volume, $usage_type, $rate, $this->balance, $plan, $row);
		if (isset($row['billrun_pretend']) && $row['billrun_pretend']) {
			Billrun_Factory::dispatcher()->trigger('afterUpdateSubscriberBalance', array(array_merge($row->getRawData(), $pricingData), $this->balance, &$pricingData, $this));
			return $pricingData;
		}

		$update = array();
		$update['$set']['tx.' . $row['stamp']] = $pricingData;
		if (!isset($this->balance->get('balance')['totals'][$balance_totals_key]['usagev'])) {
			$old_usage = 0;
		} else {
			$old_usage = $this->balance->get('balance')['totals'][$balance_totals_key]['usagev'];
		}
		$balance_id = $this->balance->getId();
		$balance_key = 'balance.totals.' . $balance_totals_key . '.usagev';
		$query = array(
			'_id' => $this->balance->getId()->getMongoID(),
			'$or' => array(
				array($balance_key => $old_usage),
				array($balance_key => array('$exists' => 0))
			)
		);

		if ($row['charging_type'] === 'postpaid') {
			$update['$set']['balance.totals.' . $balance_totals_key . '.usagev'] = $old_usage + $volume;
			$update['$inc']['balance.totals.' . $balance_totals_key . '.cost'] = $pricingData[$this->pricingField];
			$update['$inc']['balance.totals.' . $balance_totals_key . '.count'] = 1;
		} else {
			$cost = $pricingData[$this->pricingField];
			if (!is_null($this->balance->get('balance.totals.' . $balance_totals_key . '.usagev'))) {
				if ($cost > 0) { // If it's a free of charge, no need to reduce usagev
					$update['$set']['balance.totals.' . $balance_totals_key . '.usagev'] = $old_usage + $volume;
				}
			} else {
				if (!is_null($this->balance->get('balance.totals.' . $balance_totals_key . '.cost'))) {
					$update['$inc']['balance.totals.' . $balance_totals_key . '.cost'] = $cost;
				} else {
					$update['$inc']['balance.cost'] = $cost;
				}
			}
		}
		// update balance group (if exists)
		if ($plan->isRateInPlanGroup($rate, $usage_type)) {
			$group = $plan->getPlanGroup();
			if ($group !== FALSE) {
				if ($row['charging_type'] === 'postpaid') {
					// @TODO: check if $usage_type should be $key
					$update['$inc']['balance.groups.' . $group . '.' . $usage_type . '.usagev'] = $volume;
					$update['$inc']['balance.groups.' . $group . '.' . $usage_type . '.cost'] = $pricingData[$this->pricingField];
					$update['$inc']['balance.groups.' . $group . '.' . $usage_type . '.count'] = 1;
				} else {
					if (!is_null($this->balance->get('balance.totals.' . $balance_totals_key . '.usagev'))) {
						$update['$set']['balance.totals.' . $balance_totals_key . '.usagev'] = $old_usage + $volume;
					} else {
						$cost = $pricingData[$this->pricingField];
						if (!is_null($this->balance->get('balance.totals.' . $balance_totals_key . '.cost'))) {
							$update['$inc']['balance.totals.' . $balance_totals_key . '.cost'] = $cost;
						} else {
							$update['$inc']['balance.cost'] = $cost;
						}
					}
				}
				if (isset($this->balance->get('balance')['groups'][$group][$usage_type]['usagev'])) {
					$pricingData['usagesb'] = floatval($this->balance->get('balance')['balance']['groups'][$group][$usage_type]['usagev']);
				} else {
					$pricingData['usagesb'] = 0;
				}
			}
		} else {
			$pricingData['usagesb'] = floatval($old_usage);
		}
		if ($row['charging_type'] === 'postpaid') {
			$update['$set']['balance.cost'] = $balanceRaw['balance']['cost'] + $pricingData[$this->pricingField];
		}

		Billrun_Factory::log("Updating balance " . $balance_id . " of subscriber " . $row['sid'], Zend_Log::DEBUG);
		Billrun_Factory::dispatcher()->trigger('beforeCommitSubscriberBalance', array(&$row, &$pricingData, &$query, &$update, $rate, $this));
		$ret = $this->balances->update($query, $update);
		if (!($ret['ok'] && $ret['updatedExisting'])) {
			// failed because of different totals (could be that another server with another line raised the totals). 
			// Need to calculate pricingData from the beginning
			if ($this->countConcurrentRetries >= $this->concurrentMaxRetries) {
				Billrun_Factory::log()->log('Too many pricing retries for line ' . $row['stamp'] . '. Update status: ' . print_r($ret, true), Zend_Log::ALERT);
				return false;
			}
			Billrun_Factory::log('Concurrent write of sid : ' . $row['sid'] . ' line stamp : ' . $row['stamp'] . ' to balance. Update status: ' . print_r($ret, true) . 'Retrying...', Zend_Log::INFO);
			usleep($this->countConcurrentRetries);
			return $this->updateSubscriberBalance($row, $usage_type, $rate);
		}
		Billrun_Factory::log("Line with stamp " . $row['stamp'] . " was written to balance " . $balance_id . " for subscriber " . $row['sid'], Zend_Log::DEBUG);
		$row['tx_saved'] = true; // indication for transaction existence in balances. Won't & shouldn't be saved to the db.
		Billrun_Factory::dispatcher()->trigger('afterUpdateSubscriberBalance', array(array_merge($row->getRawData(), $pricingData), $this->balance, &$pricingData, $this));
		return $pricingData;
	}
	
	protected function initMinBalanceValues($rate, $usaget, $plan) {
		if (empty($this->min_balance_volume) || empty($this->min_balance_volume)) {
			$this->min_balance_volume = abs(Billrun_Factory::config()->getConfigValue('balance.minUsage.' . $usaget, Billrun_Factory::config()->getConfigValue('balance.minUsage', 0, 'float'))); // float avoid set type to int
			$this->min_balance_cost = $this->getTotalChargeByRate($rate, $usaget, $this->min_balance_volume, $plan->getName(), $this->getCallOffset());
		}
	}

	public function getPricingField() {
		return $this->pricingField;
	}

	/**
	 * method to get usage type by balances total key
	 * @param array $counters
	 * @return string
	 * @deprecated since version 2.7
	 */
	protected function getUsageKey($counters) {
		return key($counters); // array pointer will always point to the first key
	}

	/**
	 * removes the transactions from the subscriber's balance to save space.
	 * @param type $row
	 */
	public function removeBalanceTx($row) {
		if (!isset($row['balance_ref'])) {
			Billrun_Factory::log("No balance reference to remove lock", Zend_Log::WARN);
			return;
		}
		
		$balanceRef = Billrun_Factory::db()->balancesCollection()->getRef($row['balance_ref']);
		
		if (empty($balanceRef) || $balanceRef->isEmpty()) {
			Billrun_Factory::log("Balance reference is empty to remove lock", Zend_Log::WARN);
			return;
		}
		
		$query = array(
			'_id' => $balanceRef->getId()->getMongoID(),
		);
		$values = array(
			'$unset' => array(
				'tx.' . $row['stamp'] => 1
			)
		);
		$this->balances->update($query, $values);
	}

	/**
	 * @see Billrun_Calculator::getCalculatorQueueType
	 */
	public function getCalculatorQueueType() {
		return self::$type;
	}

	/**
	 * @see Billrun_Calculator::isLineLegitimate
	 */
	public function isLineLegitimate($line) {
		$arate = $this->getRateByRef($line->get('arate'));
		return !is_null($arate) && (empty($arate['skip_calc']) || !in_array(self::$type, $arate['skip_calc'])) &&
			isset($line['sid']) && $line['sid'] !== false &&
			$line['urt']->sec >= $this->billrun_lower_bound_timestamp;
	}

	/**
	 * 
	 */
	protected function setCalculatorTag($query = array(), $update = array()) {
		parent::setCalculatorTag($query, $update);
		foreach ($this->data as $item) {
			if ($this->isLineLegitimate($item) && !empty($item['tx_saved'])) {
				$this->removeBalanceTx($item); // we can safely remove the transactions after the lines have left the current queue
			}
		}
	}

	protected function loadRates() {
		$rates_coll = Billrun_Factory::db()->ratesCollection();
		$rates = $rates_coll->query()->cursor();
		foreach ($rates as $rate) {
			$rate->collection($rates_coll);
			$this->rates[strval($rate->getId())] = $rate;
		}
	}

	protected function loadPlans() {
		$plans_coll = Billrun_Factory::db()->plansCollection();
		$plans = $plans_coll->query()->cursor();
		foreach ($plans as $plan) {
			$plan->collection($plans_coll);
			$this->plans[strval($plan->getId())] = $plan;
		}
	}

	/**
	 * gets an array which represents a db ref (includes '$ref' & '$id' keys)
	 * @param type $db_ref
	 */
	public function getRowRate($row) {
		return $this->getRateByRef($row->get('arate', true));
	}

	/**
	 * gets an array which represents a db ref (includes '$ref' & '$id' keys)
	 * @param type $db_ref
	 */
	protected function getBalancePlan($sub_balance) {
		return $this->getPlanByRef($sub_balance->get('current_plan', true));
	}

	protected function getPlanByRef($plan_ref) {
		if (isset($plan_ref['$id'])) {
			$id_str = strval($plan_ref['$id']);
			if (isset($this->plans[$id_str])) {
				return $this->plans[$id_str];
			}
		}
		return null;
	}

	protected function getRateByRef($rate_ref) {
		$rates_coll = Billrun_Factory::db()->ratesCollection();
		$rate = $rates_coll->getRef($rate_ref);
		return $rate;

//		if (isset($rate_ref['$id'])) {
//			$id_str = strval($rate_ref['$id']);
//			if (isset($this->rates[$id_str])) {
//				return $this->rates[$id_str];
//			}
//		}
//		return null;
	}

	/**
	 * Add plan reference to line
	 * @param Mongodloid_Entity $row
	 * @param string $plan
	 */
	protected function addPlanRef($row, $plan) {
		$planObj = Billrun_Factory::plan(array('name' => $plan, 'time' => $row['urt']->sec, /* 'disableCache' => true */));
		if (!$planObj->get('_id')) {
			Billrun_Factory::log("Couldn't get plan for CDR line : {$row['stamp']} with plan $plan", Zend_Log::ALERT);
			return;
		}
		$row['plan_ref'] = $planObj->createRef();
		return $row->get('plan_ref', true);
	}

	/**
	 * Create a subscriber entry if none exists. Uses an update query only if the balance doesn't exist
	 * @param type $subscriber
	 * @deprecated since version 4.0
	 */
	protected static function createBalanceIfMissing($aid, $sid, $billrun_key, $plan_ref) {
		Billrun_Factory::log("Customer pricing createBalanceIfMissing is deprecated method");
		$balance = Billrun_Factory::balance(array('sid' => $sid, 'billrun_key' => $billrun_key));
		if ($balance->isValid()) {
			return $balance;
		} else {
			return Billrun_Balance::createBalanceIfMissing($aid, $sid, $billrun_key, $plan_ref);
		}
	}

	/**
	 * 
	 * @param Mongodloid_Entity $rate
	 * @param string $usage_type
	 * @param Billrun_Plan $plan
	 * @todo move to plan class
	 */
	protected function isUsageUnlimited($rate, $usage_type, $plan) {
		return ($plan->isRateInBasePlan($rate, $usage_type) && $plan->isUnlimited($usage_type)) || ($plan->isRateInPlanGroup($rate, $usage_type) && $plan->isUnlimitedGroup($rate, $usage_type));
	}

	/**
	 * Calculates the volume granted for subscriber by rate and balance
	 * @param type $row
	 * @param type $rate
	 * @param type $balance
	 * @param type $usageType
	 */
	protected function getPrepaidGrantedVolume($row, $rate, $balance, $usageType, $balanceTotalKeys = null) {
		if ($this->isFreeLine($row)) {
			return $row['usagev'];
		}
 		if (empty($balanceTotalKeys)) {
 			$balanceTotalKeys = $usageType;
 		}
		if (isset($row['api_name']) && $row['api_name'] == 'release_call') {
			return 0;
		}
		$requestedVolume = PHP_INT_MAX;
		if (isset($row['usagev'])) {
			$requestedVolume = $row['usagev'];
		}
		if ((isset($row['billrun_pretend']) && $row['billrun_pretend'] && $row['usaget'] !== 'data') ||
			(isset($row['free_call']) && $row['free_call'])) {
			return 0;
		}
		$maximumGrantedVolume = $this->getPrepaidGrantedVolumeByRate($rate, $usageType, $row['plan']);
		$rowInOrOutOfBalanceKey = 'in';
		if (isset($balance->get("balance")["totals"][$balanceTotalKeys]["usagev"])) {
			$currentBalanceVolume = $balance->get("balance")["totals"][$balanceTotalKeys]["usagev"];
		} else {
			if (isset($balance->get("balance")["totals"][$balanceTotalKeys]["cost"])) {
				$price = $balance->get("balance")["totals"][$balanceTotalKeys]["cost"];
			} else {
				$price = $balance->get("balance")["cost"];
				$rowInOrOutOfBalanceKey = 'out';
			}
			$currentBalanceVolume = $this->getVolumeByRate($rate, $usageType, abs($price), $row['plan'], $this->getCallOffset());
		}
		$currentBalanceVolume = abs($currentBalanceVolume);
		$usagev = min(array($currentBalanceVolume, $maximumGrantedVolume, $requestedVolume));
		$row[$rowInOrOutOfBalanceKey . '_balance_usage'] = $usagev;
		return $usagev;
	}

	/**
	 * Gets the maximum allowed granted volume for rate
	 * @param type $rate
	 * @param type $usageType
	 */
	protected function getPrepaidGrantedVolumeByRate($rate, $usageType, $planName) {
		if (isset($rate["rates"][$usageType]["prepaid_granted_usagev"])) {
			return $rate["rates"][$usageType]["prepaid_granted_usagev"];
		}
		if (isset($rate["rates"][$usageType]["prepaid_granted_cost"])) {
			return $this->getVolumeByRate($rate, $usageType, $rate["rates"][$usageType]["prepaid_granted_cost"], $planName, $this->getCallOffset());
		}

		$usagevDefault = Billrun_Factory::config()->getConfigValue("rates.prepaid_granted.$usageType.usagev", 0);
		if ($usagevDefault) {
			return $usagevDefault;
		}

		return $this->getVolumeByRate($rate, $usageType, Billrun_Factory::config()->getConfigValue("rates.prepaid_granted.$usageType.cost", 0), $planName, $this->getCallOffset());
	}

	protected static function getInterconnect($rate, $usage_type, $plan) {
		if (isset($rate['rates'][$usage_type][$plan]['interconnect'])) {
			return $rate['rates'][$usage_type][$plan]['interconnect'];
		}
		if (isset($rate['rates'][$usage_type]['BASE']['interconnect'])) {
			return $rate['rates'][$usage_type]['BASE']['interconnect'];
		}

		if (isset($rate['rates'][$usage_type]['interconnect'])) {
			return $rate['rates'][$usage_type]['interconnect'];
		}
	}

	public static function getPrecision() {
		return static::$precision;
	}

}
