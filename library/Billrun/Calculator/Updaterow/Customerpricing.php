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

	/**
	 * row plan details
	 * 
	 * @param array
	 */
	protected $plan;

	protected function init() {
		$this->rate = $this->getRowRate($this->row);
		$planSettings = array(
			'name' => $this->row['plan'],
			'time' => $this->row['urt']->sec,
		);
		$this->plan = Billrun_Factory::plan($planSettings);
		$this->setCallOffset(isset($this->row['call_offset']) ? $this->row['call_offset'] : 0);
		// max recursive retryes for value=oldValue tactic
		$this->concurrentMaxRetries = (int) Billrun_Factory::config()->getConfigValue('updateValueEqualOldValueMaxRetries', 8);
		$this->pricingField = $this->calculator->getPricingField(); // todo remove this coupling
	}

	/**
	 * generic validation step before start the update row
	 * 
	 * @return boolean true on valid else false
	 */
	protected function validate() {
		return true;
	}

	public function update() {
		if (!$this->validate()) {
			return false;
		}
		$this->countConcurrentRetries = 0;
		//TODO  change this to be configurable.
		$pricingData = array();
		$volume = isset($this->row['usagev']) ? $this->row['usagev'] : null;
		$typesWithoutBalance = Billrun_Factory::config()->getConfigValue('customerPricing.calculator.typesWithoutBalance', array('credit', 'service'));
		if (in_array($this->row['type'], $typesWithoutBalance)) {
			$charges = Billrun_Rates_Util::getTotalCharge($this->rate, $this->usaget, $volume, $this->row['plan'], $this->getCallOffset());
			$pricingData = array($this->pricingField => $charges['total']);
		} else {
			$pricingData = $this->updateSubscriberBalance($this->usaget, $this->rate);
		}
		
		if ($pricingData === false) {
			return false;
		}

		if (!$this->isBillable($this->rate)) {
			return $pricingData;
		}

		$pricingData['billrun'] = $this->row['urt']->sec <= $this->active_billrun_end_time ? $this->active_billrun : $this->next_active_billrun;

		return $pricingData;
	}

	/**
	 * Determines if a rate should not produce billable lines, but only counts the usage
	 * 
	 * @return boolean
	 */
	protected function isBillable() {
		return Billrun_Rates_Util::isBillable($this->rate);
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
		if (!$this->loadSubscriberBalance($this->row, $this->min_balance_volume, $this->min_balance_cost) && // will load $this->balance
			($balanceNoAvailableResponse = $this->handleNoBalance($this->row, $this->rate, $this->plan)) !== TRUE) {
			return $balanceNoAvailableResponse;
		}

		Billrun_Factory::dispatcher()->trigger('beforeUpdateSubscriberBalance', array($this->balance, &$this->row, $this->rate, $this));
		$pricingData = $this->balance->updateBalanceByRow($this->row, $this->rate, $this->plan, $this->usaget, $this->usagev);
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

	/**
	 * method that handle cases when balance is not available (on real-time)
	 * @return boolean true if you want to continue even if there is no available balance else false
	 */
	protected function handleNoBalance() {
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

}
