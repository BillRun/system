<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator update row for customer pricing calc in row level
 *
 * @package     calculator
 * @subpackage  updaterow
 * @since       5.3
 */
class Billrun_Calculator_Updaterow_Customerpricing extends Billrun_Calculator_Updaterow {

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
	 * balance that customer pricing update
	 * 
	 * @param Billrun_Balance
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
	 * @param int
	 */
	protected $call_offset = 0;

	/**
	 * row rate details
	 * 
	 * @param array
	 */
	protected $rate;

	protected function init() {
		$this->rate = $this->getRowRate($this->row);
		$this->setCallOffset(isset($this->row['call_offset']) ? $this->row['call_offset'] : 0);
		// max recursive retryes for value=oldValue tactic
		$this->concurrentMaxRetries = (int) Billrun_Factory::config()->getConfigValue('updateValueEqualOldValueMaxRetries', 8);
		$this->pricingField = $this->calculator->getPricingField(); // todo remove this coupling
	}

	public function update() {
		$this->countConcurrentRetries = 0;

		if (!isset($this->row['usagev']) && !self::isPrepaid($this->row)) {  // for prepaid, volume calculated by balance left over
			Billrun_Factory::log("Line with stamp " . $this->row['stamp'] . " is missing volume information", Zend_Log::ALERT);
			return false;
		}


		//TODO  change this to be configurable.
		$pricingData = array();
		$volume = isset($this->row['usagev']) ? $this->row['usagev'] : null;
		$typesWithoutBalance = Billrun_Factory::config()->getConfigValue('customerPricing.calculator.typesWithoutBalance', array('credit', 'service'));
		if (in_array($this->row['type'], $typesWithoutBalance)) {
			$charges = Billrun_Rates_Util::getTotalCharge($this->rate, $this->usaget, $volume, $this->row['plan'], $this->getCallOffset());
			$pricingData = array($this->pricingField => $charges['total']);
		} else if (($pricingData = $this->updateSubscriberBalance($this->usaget, $this->rate)) === FALSE) {
			return self::isPrepaid($this->row); // prepaid hack - on prepaid return true and false on post paid
		}

		if (Billrun_Rates_Util::isBillable($this->rate)) {
			// billrun cannot override on api calls
			if (!self::isPrepaid($this->row) && (!isset($this->row['billrun']) || $this->row['source'] != 'api')) {
				$pricingData['billrun'] = $this->row['urt']->sec <= $this->active_billrun_end_time ? $this->active_billrun : $this->next_active_billrun;
			}
		}


		return $pricingData;
	}

	/**
	 * Update the subscriber balance for a given usage
	 * Method is recursive - it tries to update subscriber balances with value=oldValue tactic
	 * There is max retries for the recursive to run and the value is configured
	 * 
	 * @return mixed array with the pricing data on success, false otherwise
	 * @todo separate prepaid & postpaid logic to inheritance classes
	 * 
	 */
	protected function updateSubscriberBalance() {
		if (self::isPrepaid($this->row)) {
			$this->row['granted_return_code'] = Billrun_Factory::config()->getConfigValue('prepaid.ok');
		}
		$planSettings = array(
			'name' => $this->row['plan'],
			'time' => $this->row['urt']->sec,
		);
		$plan = Billrun_Factory::plan($planSettings);
		if (self::isPrepaid($this->row)) {
			$this->initMinBalanceValues($this->rate, $this->row['usaget'], $plan);
		} else {
			$this->min_balance_volume = null;
			$this->min_balance_cost = null;
		}
		if (!$this->loadSubscriberBalance($this->row, $this->min_balance_volume, $this->min_balance_cost) && // will load $this->balance
			($balanceNoAvailableResponse = $this->handleNoBalance($this->row, $this->rate, $plan)) !== TRUE) {
			return $balanceNoAvailableResponse;
		}

		if (self::isPrepaid($this->row) && !(isset($this->row['prepaid_rebalance']) && $this->row['prepaid_rebalance'])) { // If it's a prepaid row, but not rebalance
			$this->row['apr'] = Billrun_Rates_Util::getTotalChargeByRate($this->rate, $this->row['usaget'], $this->row['usagev'], $this->row['plan'], $this->getCallOffset());
			if (!$this->balance && self::isFreeLine($this->row)) {
				return $this->balance->getFreeRowPricingData();
			}
			$this->row['balance_ref'] = $this->balance->createRef();
			$this->row['usagev'] = $volume = Billrun_Rates_Util::getPrepaidGrantedVolume($this->row, $this->rate, $this->balance, $this->usaget, $this->balance->getBalanceChargingTotalsKey(sagesage_type), $this->getCallOffset(), $this->min_balance_cost, $this->min_balance_volume);
		} else {
			$volume = $this->usagev;
		}

		Billrun_Factory::dispatcher()->trigger('beforeUpdateSubscriberBalance', array($this->balance, &$this->row, $this->rate, $this));
		$pricingData = $this->balance->updateBalanceByRow($this->row, $this->rate, $plan, $this->usaget, $volume);
		if ($pricingData === false) {
			// failed because of different totals (could be that another server with another line raised the totals). 
			// Need to calculate pricingData from the beginning
			if (++$this->countConcurrentRetries >= $this->concurrentMaxRetries) {
				Billrun_Factory::log()->log('Too many pricing retries for line stamp: ' . $this->row['stamp'], Zend_Log::ALERT);
				return false;
			}
			usleep($this->countConcurrentRetries);
			return $this->updateSubscriberBalance($this->row, $this->usaget, $this->rate);
		}
		Billrun_Factory::dispatcher()->trigger('afterUpdateSubscriberBalance', array(array_merge($this->row->getRawData(), $pricingData), $this->balance, &$pricingData, $this));
		return $pricingData;
	}

	protected function handleNoBalance($plan) {
		if (self::isPrepaid($this->row)) {
			// check first if this free call and allow it if so
			if ($this->min_balance_cost == '0') {
				if (isset($this->row['api_name']) && in_array($this->row['api_name'], array('start_call', 'release_call'))) {
					$granted_volume = 0;
				} else {
					$granted_volume = Billrun_Rates_Util::getPrepaidGrantedVolumeByRate($this->rate, $this->row['usaget'], $plan->getName(), $this->getCallOffset(), $this->min_balance_cost, $this->min_balance_volume);
				}
				$charges = Billrun_Rates_Util::getChargesByRate($this->rate, $this->row['usaget'], $granted_volume, $plan->getName(), $this->getCallOffset());
				$granted_cost = $charges['total'];
				return array(
					$this->pricingField => $granted_cost,
					'usagev' => $granted_volume,
				);
			}
			$this->row['granted_return_code'] = Billrun_Factory::config()->getConfigValue('prepaid.customer.no_available_balances');
		}
		Billrun_Factory::dispatcher()->trigger('afterSubscriberBalanceNotFound', array(&$this->row));
		if ($this->row['usagev'] === 0) {
			return false;
		}
		return true;
	}

	/**
	 * Gets the subscriber balance. If it does not exist, creates it.
	 * 
	 * @param type $row
	 * 
	 * @return Billrun_Balance
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
		$instanceOptions['balance_db_refresh'] = true;
		$loadedBalance = Billrun_Balance::getInstance($instanceOptions);
		if (!$loadedBalance || !$loadedBalance->isValid()) {
			Billrun_Factory::log("couldn't get balance for subscriber: " . $row['sid'], Zend_Log::INFO);
			$row['usagev'] = 0;
			$row['apr'] = 0;
			return false;
		} else {
			Billrun_Factory::log("Found balance for subscriber " . $row['sid'], Zend_Log::DEBUG);
		}
		$this->balance = $loadedBalance;
		return true;
	}

	protected function initMinBalanceValues($usaget, $plan) {
		if (empty($this->min_balance_volume) || empty($this->min_balance_volume)) {
			$this->min_balance_volume = abs(Billrun_Factory::config()->getConfigValue('balance.minUsage.' . $usaget, Billrun_Factory::config()->getConfigValue('balance.minUsage', 0, 'float'))); // float avoid set type to int
			$this->min_balance_cost = Billrun_Rates_Util::getTotalChargeByRate($this->rate, $usaget, $this->min_balance_volume, $plan->getName(), $this->getCallOffset());
		}
	}

	/**
	 * gets an array which represents a db ref (includes '$ref' & '$id' keys)
	 * @param type $db_ref
	 */
	public function getRowRate($row) {
		return Billrun_Rates_Util::getRateByRef($this->row->get('arate', true));
	}

	/**
	 * check if row is prepaid
	 * 
	 * @param array $row row handled by the calculator
	 * 
	 * @return boolean true it it's prepaid row
	 * @todo refactoring prepaid to strategy pattern
	 */
	protected function isPrepaid() {
		return $this->charging_type === 'prepaid';
	}

	public function setCallOffset($val) {
		$this->call_offset = $val;
	}

	public function getCallOffset() {
		return $this->call_offset;
	}
	
	/**
	 * plugin-able check if line is free
	 * 
	 * @param array $row row to check
	 * 
	 * @return true if it's free row else false
	 */
	public static function isFreeLine(&$row) {
		if (self::isPrepaid($row)) {
			$isFreeLine = false;
			Billrun_Factory::dispatcher()->trigger('isFreeLine', array(&$row, &$isFreeLine));
			if ($isFreeLine) {
				$row['free_line'] = true;
			}
			return $isFreeLine;
		}
		return false;
	}

}
