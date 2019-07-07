<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
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

	protected $pricingField = self::DEF_CALC_DB_FIELD;
	static protected $type = "pricing";

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

	public function __construct($options = array()) {
		if (isset($options['autoload'])) {
			$autoload = $options['autoload'];
		} else {
			$autoload = true;
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
		$this->loadRates();
		$this->loadPlans();
		$this->balances = Billrun_Factory::db(array('name' => 'balances'))->balancesCollection()->setReadPreference('RP_PRIMARY');
		$this->active_billrun = Billrun_Billrun::getActiveBillrun();
		$this->active_billrun_end_time = Billrun_Util::getEndTime($this->active_billrun);
		$this->next_active_billrun = Billrun_Util::getFollowingBillrunKey($this->active_billrun);
		// max recursive retrues for value=oldValue tactic
		$this->concurrentMaxRetries = (int) Billrun_Factory::config()->getConfigValue('updateValueEqualOldValueMaxRetries', 8);
		try {
			$this->sidsQueuedForRebalance = @array_flip(Billrun_Factory::db()->rebalance_queueCollection()->distinct('sid'));
		} catch (Exception $e) {
			Billrun_Factory::log("Failed when trying to get the  sids in rebalance queue");
		}
	}

	protected function getLines() {
		$query = array();
		$query['type'] = array('$in' => array('ggsn', 'smpp', 'mmsc', 'smsc', 'nsn', 'tap3', 'credit'));
		return $this->getQueuedLines($query);
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
				//Billrun_Factory::log()->log("Calculating row: ".print_r($item,1),  Zend_Log::DEBUG);
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
			Billrun_Factory::dispatcher()->trigger('beforeCalculatorUpdateRow', array($row, $this));
			$billrun_key = Billrun_Util::getBillrunKey($row->get('urt')->sec);
			$rate = $this->getRowRate($row);

			//TODO  change this to be configurable.
			$pricingData = array();

			$usage_type = $row['usaget'];
			$volume = $row['usagev'];

			if (isset($volume)) {
				if ($row['type'] == 'credit') {
					$accessPrice = isset($rate['rates'][$usage_type]['access']) ? $rate['rates'][$usage_type]['access'] : 0;
					$pricingData = array($this->pricingField => $accessPrice + self::getPriceByRate($rate, $usage_type, $volume));
				} else if ($row['type'] == 'service') {
					$pricingData = array($this->pricingField => self::getPriceByRate($rate, $row['type'], $volume));
					if (isset($row['fraction'])) {
						$pricingData['aprice'] = $pricingData['aprice'] * $row['fraction'];
					}
				} else {
					$balance = $this->getSubscriberBalance($row, $billrun_key);
					if ($balance === FALSE) {
						return false;
					}
					$pricingData = $this->updateSubscriberBalance($balance, $row, $usage_type, $rate, $volume);
				}

				if ($this->isBillable($rate)) {
					if (!$pricingData) {
						return false;
					}

					// billrun cannot override on api calls
					if (!isset($row['billrun']) || $row['source'] != 'api') {
						$pricingData['billrun'] = $row['urt']->sec <= $this->active_billrun_end_time ? $this->active_billrun : $this->next_active_billrun;
					}
				}
			} else {
				Billrun_Factory::log()->log("Line with stamp " . $row['stamp'] . " is missing volume information", Zend_Log::ALERT);
				return false;
			}

			$pricingDataTxt = "Saving pricing data to line with stamp: " . $row['stamp'] . ".";
			foreach ($pricingData as $key => $value) {
				if ($key == 'roaming_balances') {
					continue;
				}
				$pricingDataTxt .= " " . $key . ": " . $value . ".";
			}
			Billrun_Factory::log()->log($pricingDataTxt, Zend_Log::DEBUG);
			$row->setRawData(array_merge($row->getRawData(), $pricingData));

			Billrun_Factory::dispatcher()->trigger('afterCalculatorUpdateRow', array($row, $this));
			return $row;
		} catch (Exception $e) {
			Billrun_Factory::log()->log('Line with stamp ' . $row['stamp'] . ' crashed when trying to price it. got exception :' . $e->getCode() . ' : ' . $e->getMessage() . "\n trace :" . $e->getTraceAsString(), Zend_Log::ERR);
			return false;
		}
	}

	/**
	 * Gets the subscriber's balance. If it does not exist, creates it.
	 * @param type $row
	 * @param type $billrun_key
	 * @return Billrun_Balance
	 */
	public function getSubscriberBalance($row, $billrun_key) {
		$plan = Billrun_Factory::plan(array('name' => $row['plan'], 'time' => $row['urt']->sec, 'disableCache' => true));
		$plan_ref = $plan->createRef();
		if (is_null($plan_ref)) {
			Billrun_Factory::log('No plan found for subscriber ' . $row['sid'], Zend_Log::ALERT);
			return false;
		}
		$balance_unique_key = array('sid' => $row['sid'], 'billrun_key' => $billrun_key, 'unique_plan_id' => $row['unique_plan_id']);
		if (!($balance = self::createBalanceIfMissing($row['aid'], $row['sid'], $billrun_key, $plan_ref, $row['unique_plan_id']))) {
			return false;
		} else if ($balance === true) {
			$balance = null;
		}

		if (is_null($balance)) {
			$balance = Billrun_Factory::balance($balance_unique_key);
		}
		if (!$balance || !$balance->isValid()) {
			Billrun_Factory::log()->log("couldn't get balance for : " . print_r(array(
					'sid' => $row['sid'],
					'billrun_month' => $billrun_key,
					'unique_plan_id' => $row['unique_plan_id']
					), 1), Zend_Log::INFO);
			return false;
		} else {
			Billrun_Factory::log()->log("Found balance " . $billrun_key . " for subscriber " . $row['sid'] . ', unique_plan_id=' . $row['unique_plan_id'], Zend_Log::DEBUG);
		}
		return $balance;
	}

	/** Deprecated should not work
	 * get subscriber plan object
	 * identification using the balance collection
	 * 
	 * @param array $sub_balance the subscriber balance
	 * @return type
	 * @deprecated
	 */
	protected function getPlan($sub_balance) {
		$subscriber_current_plan = $this->getBalancePlan($sub_balance);
		return Billrun_Factory::plan(array('data' => $subscriber_current_plan));
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
	protected function getLinePricingData($volume, $usageType, $rate, $sub_balance, $plan) {
		$accessPrice = isset($rate['rates'][$usageType]['access']) ? $rate['rates'][$usageType]['access'] : 0;
		$ret = array();
		if ($plan->isRateInBasePlan($rate, $usageType)) {
			$planVolumeLeft = $plan->usageLeftInBasePlan($sub_balance, $rate, $usageType);
			$volumeToCharge = $volume - $planVolumeLeft;
			if ($volumeToCharge < 0) {
				$volumeToCharge = 0;
				$ret['in_plan'] = $volume;
				$accessPrice = 0;
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
				$accessPrice = 0;
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
			if (($plan->getPlanGroup() !== FALSE) && (!empty($ret['in_plan']))) {
				$ret['arategroup'] = $plan->getPlanGroup();
			}
		} else { // else if (dispatcher->chain_of_responsibilty)->isRateInPlugin {dispatcher->trigger->calc}
			$ret['out_plan'] = $volumeToCharge = $volume;
		}

		$price = $accessPrice + self::getPriceByRate($rate, $usageType, $volumeToCharge);
		//Billrun_Factory::log()->log("Rate : ".print_r($typedRates,1),  Zend_Log::DEBUG);
		$ret[$this->pricingField] = $price;
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
		$saveProperties = array($this->pricingField, 'billrun', 'over_plan', 'in_plan', 'out_plan', 'plan_ref', 'usagesb', 'arategroup', 'over_arate', 'over_group', 'in_group', 'in_arate', 'vf_count_days', 'roaming_balances', 'addon_balances', 'plan_usage');
		foreach ($saveProperties as $p) {
			if (!is_null($val = $line->get($p, true))) {
				$save['$set'][$p] = $val;
			}
		}
		$where = array('stamp' => $line['stamp']);
		if ($save) {
			Billrun_Factory::db()->linesCollection()->update($where, $save);
			Billrun_Factory::db()->queueCollection()->update($where, $save);
		}
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteLine', array('data' => $line, 'calculator' => $this));
		if (!isset($line['usagev']) || $line['usagev'] === 0) {
			$this->garbageQueueLines[] = $line['stamp'];
			unset($this->data[$dataKey]);
		}
	}

	/**
	 * Calculates the price for the given volume (w/o access price)
	 * @param array $rate the rate entry
	 * @param string $usage_type the usage type
	 * @param int $volume The usage volume (seconds of call, count of SMS, bytes  of data)
	 * @return int the calculated price
	 */
	public static function getPriceByRate($rate, $usage_type, $volume) {
		$rates_arr = $rate['rates'][$usage_type]['rate'];
		$price = 0;
		foreach ($rates_arr as $currRate) {
			if (0 == $volume) { // volume could be negative if it's a refund amount
				break;
			}//break if no volume left to price.
			$volumeToPriceCurrentRating = ($volume - $currRate['to'] < 0) ? $volume : $currRate['to']; // get the volume that needed to be priced for the current rating
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
		}
		return $price;
	}

	/**
	 * Update the subscriber balance for a given usage
	 * Method is recursive - it tries to update subscriber balances with value=oldValue tactic
	 * There is max retries for the recursive to run and the value is configured
	 * 
	 * @param Mongodloid_Entity $row the input line
	 * @param string $billrun_key the billrun key at the row time
	 * @param string $usage_type The type  of the usage (call/sms/data)
	 * @param mixed $rate The rate of associated with the usage.
	 * @param int $volume The usage volume (seconds of call, count of SMS, bytes  of data)
	 * 
	 * @return mixed array with the pricing data on success, false otherwise
	 * @todo refactoring and make it more abstract
	 * @todo create unit test for this method because it's critical method
	 * 
	 */
	protected function updateSubscriberBalance($balance, $row, $usage_type, $rate, $volume) {
		$this->countConcurrentRetries++;
		Billrun_Factory::dispatcher()->trigger('beforeUpdateSubscriberBalance', array($balance, &$row, $rate, $this));
		$plan = Billrun_Factory::plan(array('name' => $row['plan'], 'time' => $row['urt']->sec, 'disableCache' => true));
		$balance_totals_key = $plan->getBalanceTotalsKey($usage_type, $rate);
		$counters = array($balance_totals_key => $volume);
		$subRaw = $balance->getRawData();
		$stamp = strval($row['stamp']);
		if (isset($subRaw['tx']) && array_key_exists($stamp, $subRaw['tx'])) { // we're after a crash
			$pricingData = $subRaw['tx'][$stamp]; // restore the pricingData before the crash
			$row['tx_saved'] = true; // indication for transaction existence in balances. Won't & shouldn't be saved to the db.
			Billrun_Factory::dispatcher()->trigger('handleExtraBalancesOnCrash', array(&$pricingData, $row));
			return $pricingData;
		}
		$pricingData = $this->getLinePricingData($volume, $usage_type, $rate, $balance, $plan);
		$query = array('sid' => $row['sid'], 'billrun_month' => $balance['billrun_month'], 'unique_plan_id' => $row['unique_plan_id']);
		$update = array();
		$update['$set']['tx.' . $stamp] = $pricingData;
		foreach ($counters as $key => $value) {
			$old_usage = $subRaw['balance']['totals'][$key]['usagev'];
			$query['balance.totals.' . $key . '.usagev'] = $old_usage;
			$update['$set']['balance.totals.' . $key . '.usagev'] = $old_usage + $value;
			$update['$inc']['balance.totals.' . $key . '.cost'] = $pricingData[$this->pricingField];
			$update['$inc']['balance.totals.' . $key . '.count'] = 1;
			// update balance group (if exists)
			if ($plan->isRateInPlanGroup($rate, $usage_type)) {
				$group = $plan->getPlanGroup();
				if ($group !== FALSE && !is_null($group)) {
					// @TODO: check if $usage_type should be $key
					$update['$inc']['balance.groups.' . $group . '.' . $usage_type . '.usagev'] = $value;
					$update['$inc']['balance.groups.' . $group . '.' . $usage_type . '.cost'] = $pricingData[$this->pricingField];
					$update['$inc']['balance.groups.' . $group . '.' . $usage_type . '.count'] = 1;
					if (isset($subRaw['balance']['groups'][$group][$usage_type]['usagev'])) {
						$pricingData['usagesb'] = floatval($subRaw['balance']['groups'][$group][$usage_type]['usagev']);
					} else {
						$pricingData['usagesb'] = 0;
					}
				}
			} else {
				$pricingData['usagesb'] = floatval($old_usage);
			}
		}
		$update['$set']['balance.cost'] = $subRaw['balance']['cost'] + $pricingData[$this->pricingField];
		$options = array('w' => 1);
		$is_data_usage = ($balance_totals_key == 'data');
		if ($is_data_usage) {
			$this->setMongoNativeLong(1);
		}
		Billrun_Factory::log()->log("Updating balance " . $balance['billrun_month'] . " of subscriber " . $row['sid'], Zend_Log::DEBUG);
		Billrun_Factory::dispatcher()->trigger('beforeCommitSubscriberBalance', array(&$row, &$pricingData, &$query, &$update, $rate, $this));
		if ($update) {
			$ret = $this->balances->update($query, $update, $options);
		} else {
			if ($is_data_usage) {
				$this->setMongoNativeLong(0);
			}
			return $pricingData;
		}
		if ($is_data_usage) {
			$this->setMongoNativeLong(0);
		}
		if (!($ret['ok'] && $ret['updatedExisting'])) {
			// failed because of different totals (could be that another server with another line raised the totals). 
			// Need to calculate pricingData from the beginning
			if ($this->countConcurrentRetries >= $this->concurrentMaxRetries) {
				Billrun_Factory::log()->log('Too many pricing retries for line ' . $row['stamp'] . '. Update status: ' . print_r($ret, true), Zend_Log::ALERT);
				return false;
			}
			Billrun_Factory::log()->log('Concurrent write of sid : ' . $row['sid'] . ' line stamp : ' . $row['stamp'] . ' to balance. Update status: ' . print_r($ret, true) . 'Retrying...', Zend_Log::INFO);
			sleep($this->countConcurrentRetries);
			$balance = $this->getSubscriberBalance($row, $balance['billrun_month']);
			return $this->updateSubscriberBalance($balance, $row, $usage_type, $rate, $volume);
		}
		Billrun_Factory::log()->log("Line with stamp " . $row['stamp'] . " was written to balance " . $balance['billrun_month'] . " for subscriber " . $row['sid'], Zend_Log::DEBUG);
		$row['tx_saved'] = true; // indication for transaction existence in balances. Won't & shouldn't be saved to the db.
		Billrun_Factory::dispatcher()->trigger('afterUpdateSubscriberBalance', array(array_merge($row->getRawData(), $pricingData), $balance, &$pricingData, $this));
		return $pricingData;
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
	 * method to set MongoDB native long
	 * this is useful only on MongoDB 2.4 and below because the native long is off by default
	 * 
	 * @param int $status either 1 to turn on or 0 for off
	 */
	protected function setMongoNativeLong($status = 1) {
		Billrun_Factory::db()->setMongoNativeLong($status);
	}

	/**
	 * method to increase subscriber balance without lock nor transaction
	 * 
	 * @deprecated since version 2.7
	 */
	protected function increaseSubscriberBalance($counters, $billrun_key, $aid, $sid, $plan_ref) {
		$query = array('sid' => $sid, 'billrun_month' => $billrun_key);
		$update = array('$inc' => array());
		foreach ($counters as $key => $value) {
			$update['$inc']['balance.totals.' . $key . '.usagev'] = $value;
			$update['$inc']['balance.totals.' . $key . '.count'] = 1;
		}
		$is_data_usage = $this->getUsageKey($counters) == 'data';
		if ($is_data_usage) {
			$this->setMongoNativeLong(1);
		}
		Billrun_Factory::log()->log("Increasing subscriber $sid balance " . $billrun_key, Zend_Log::DEBUG);
		$balance = $this->balances->findAndModify($query, $update, array(), array());
		if ($is_data_usage) {
			$this->setMongoNativeLong(0);
		}
		if ($balance->isEmpty()) {
			Billrun_Factory::log()->log('Balance ' . $billrun_key . ' does not exist for subscriber ' . $sid . '. Creating...', Zend_Log::INFO);
			Billrun_Balance::createBalanceIfMissing($aid, $sid, $billrun_key, $plan_ref);
			return $this->increaseSubscriberBalance($counters, $billrun_key, $aid, $sid, $plan_ref);
		} else {
			Billrun_Factory::log()->log("Found balance " . $billrun_key . " for subscriber " . $sid, Zend_Log::DEBUG);
		}
		return Billrun_Factory::balance(array('data' => $balance));
	}

	/**
	 * removes the transactions from the subscriber's balance to save space.
	 * @param type $row
	 */
	public function removeBalanceTx($row) {
		$sid = $row['sid'];
		$billrun_key = Billrun_Util::getBillrunKey($row['urt']->sec);
		$query = array(
			'billrun_month' => $billrun_key,
			'sid' => $sid,
			'unique_plan_id' => $row['unique_plan_id']
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
		if ($line['type'] == 'tap3' && $line['usaget'] == 'sms') {
			return false;
		}
		$arate = $this->getRateByRef($line->get('arate', true));
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
	protected function getRowRate($row) {
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
		if (isset($rate_ref['$id'])) {
			$id_str = strval($rate_ref['$id']);
			if (isset($this->rates[$id_str])) {
				return $this->rates[$id_str];
			}
		}
		return null;
	}

	/**
	 * Add plan reference to line
	 * @param Mongodloid_Entity $row
	 * @param string $plan
	 */
	protected function addPlanRef($row, $plan) {
		$planObj = Billrun_Factory::plan(array('name' => $plan, 'time' => $row['urt']->sec, 'disableCache' => true));
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
	 */
	protected static function createBalanceIfMissing($aid, $sid, $billrun_key, $plan_ref, $uniquePlanId) {
		$balance = Billrun_Factory::balance(array('sid' => $sid, 'billrun_key' => $billrun_key, 'unique_plan_id' => $uniquePlanId));
		if ($balance->isValid()) {
			return $balance;
		} else {
			return Billrun_Balance::createBalanceIfMissing($aid, $sid, $billrun_key, $plan_ref, $uniquePlanId);
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

}
