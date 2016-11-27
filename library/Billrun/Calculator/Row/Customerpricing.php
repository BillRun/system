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
 * @subpackage  row
 * @since       5.3
 */
class Billrun_Calculator_Row_Customerpricing extends Billrun_Calculator_Row {

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
		if (!$this->loadSubscriberBalance() && // will load $this->balance
			($balanceNoAvailableResponse = $this->handleNoBalance($this->row, $this->rate, $this->plan)) !== TRUE) {
			return $balanceNoAvailableResponse;
		}

		Billrun_Factory::dispatcher()->trigger('beforeUpdateSubscriberBalance', array($this->balance, &$this->row, $this->rate, $this));
		$pricingData = $this->updateBalanceByRow($this->row, $this->rate, $this->plan, $this->usaget, $this->usagev);
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
	 * @return boolean
	 */
	public function loadSubscriberBalance() {
		// we moved the init of plan_ref to customer calc, we leave it here only for verification and avoid b/c issues
		if (!isset($this->row['plan_ref'])) {
			$plan = Billrun_Factory::plan(array('name' => $this->plan, 'time' => $this->urt->sec, /* 'disableCache' => true */));
			$plan_ref = $plan->createRef();
			if (is_null($plan_ref)) {
				Billrun_Factory::log('No plan found for subscriber ' . $this->sid, Zend_Log::ALERT);
				$this->usagev = 0;
				$this->apr = 0;
				return false;
			}
			$this->plan_ref = $plan_ref;
		}
		$instanceOptions = array_merge($this->row->getRawData(), array('granted_usagev' => $this->granted_volume, 'granted_cost' => $this->granted_cost));
		$instanceOptions['balance_db_refresh'] = true;
		if ($this->plan->isGroupAccountShared($this->rate, $this->usaget)) {
			$instanceOptions['sid'] = 0;
		}
		$loadedBalance = Billrun_Balance::getInstance($instanceOptions);
		if (!$loadedBalance || !$loadedBalance->isValid()) {
			Billrun_Factory::log("couldn't get balance for subscriber: " . $this->sid, Zend_Log::INFO);
			$this->usagev = 0;
			$this->apr = 0;
			return false;
		} else {
			Billrun_Factory::log("Found balance for subscriber " . $this->sid, Zend_Log::DEBUG);
		}
		$this->balance = $loadedBalance;
		return true;
	}

	protected function initMinBalanceValues() {
		if (empty($this->min_balance_volume) || empty($this->min_balance_volume)) {
			$this->min_balance_volume = abs(Billrun_Factory::config()->getConfigValue('balance.minUsage.' . $this->usaget, Billrun_Factory::config()->getConfigValue('balance.minUsage', 0, 'float'))); // float avoid set type to int
			$this->min_balance_cost = Billrun_Rates_Util::getTotalChargeByRate($this->rate, $this->usaget, $this->min_balance_volume, $this->plan->getName(), $this->getCallOffset());
		}
	}

	/**
	 * gets an array which represents a db ref (includes '$ref' & '$id' keys)
	 * @param type $db_ref
	 */
	public function getRowRate($row) {
		return Billrun_Rates_Util::getRateByRef($row->get('arate', true));
	}

	public function setCallOffset($val) {
		$this->call_offset = $val;
	}

	public function getCallOffset() {
		return $this->call_offset;
	}

	/**
	 * method to update subscriber balance to db
	 * 
	 * @param Mongodloid_Entity $row the input line
	 * @param mixed $rate The rate of associated with the usage.
	 * @param Billrun_Plan $plan the customer plan
	 * @param string $usage_type The type  of the usage
	 * @param int $volume The usage volume (seconds of call, count of SMS, bytes  of data)
	 * 
	 * @return mixed on success update return pricing data array, else false
	 * 
	 */
	public function updateBalanceByRow($row, $rate, $plan, $usage_type, $volume) {		
		$pricingData = $this->getLinePricingData($volume, $usage_type, $rate, $plan, $row);
		if (isset($row['billrun_pretend']) && $row['billrun_pretend']) {
			Billrun_Factory::dispatcher()->trigger('afterUpdateSubscriberBalance', array(array_merge($row->getRawData(), $pricingData), $this, &$pricingData, $this));
			return $pricingData;
		}
		
		if (!isset($pricingData['arategroups'])) {
			return $pricingData;
		}

		foreach ($pricingData['arategroups'] as &$balanceData) {
			$balance = $balanceData[0]['balance'];
			if (($crashedPricingData = $this->getTx($row['stamp'], $balance)) !== FALSE) {
				return $crashedPricingData;
			}

			if (!isset($balanceData[0]['balance'])) {
				Billrun_Factory::log("No balance reference on pricing data", Zend_Log::ERR);
				continue;
			}
			
			foreach ($balanceData as &$data) {
				unset($data['balance']);
			}
			
			$balancePricingData = $pricingData;
			$balancePricingData['arategroups'] = $balanceData;
			
			$balance_id = $balance->getId();
			Billrun_Factory::log("Updating balance " . $balance_id . " of subscriber " . $row['sid'], Zend_Log::DEBUG);
			list($query, $update) = $this->balance->buildBalanceUpdateQuery($balancePricingData, $row, $volume);

			Billrun_Factory::dispatcher()->trigger('beforeCommitSubscriberBalance', array(&$row, &$balancePricingData, &$query, &$update, $rate, $this));
			$ret = $this->balance->update($query, $update);
			if (!($ret['ok'] && $ret['updatedExisting'])) {
				Billrun_Factory::log('Update subscriber balance failed on updated existing document. Update status: ' . print_r($ret, true), Zend_Log::INFO);
				return false;
			}
			Billrun_Factory::log("Line with stamp " . $row['stamp'] . " was written to balance " . $balance_id . " for subscriber " . $row['sid'], Zend_Log::DEBUG);
			$row['tx_saved'] = true; // indication for transaction existence in balances. Won't & shouldn't be saved to the db.
		}
		
		return $pricingData;
	}

	/**
	 * try to fetch previous calculation which is not complete
	 * 
	 * @param string $stamp the row stamp
	 * 
	 * @return mixed false if not found transaction, else the transaction info
	 */
	protected function getTx($stamp, $balance) {
		$tx = $balance->get('tx');
		if (is_array($tx) && empty($tx)) {
			$balance->set('tx', new stdClass());
			$balance->save();
		}
		if (!empty($tx) && array_key_exists($stamp, $tx)) { // we're after a crash
			$pricingData = $tx[$stamp]; // restore the pricingData before the crash
			return $pricingData;
		}
		return false;
	}

	/**
	 * Get pricing data for a given rate / subscriber.
	 * 
	 * @param int $volume The usage volume (seconds of call, count of SMS, bytes  of data)
	 * @param string $usageType The type  of the usage (call/sms/data)
	 * @param mixed $rate The rate of associated with the usage.
	 * @param Billrun_Plan $plan the subscriber's current plan
	 * @param array $row the row handle
	 * @return array pricing data details of the specific volume
	 * 
	 * @todo refactoring the if-else-if-else-if-else to methods
	 * @todo remove (in/over/out)_plan support (group used instead)
	 */
	protected function getLinePricingData($volume, $usageType, $rate, $plan, $row = null) {
		$ret = array();
		$balanceId = (string) $this->balance->getId();
		if ($plan->isRateInEntityGroup($rate, $usageType)) {
			$groupVolumeLeft = $plan->usageLeftInEntityGroup($this->balance, $rate, $usageType);
			$volumeToCharge = $volume - $groupVolumeLeft;
			if ($volumeToCharge < 0) {
				$volumeToCharge = 0;
				$ret['in_group'] = $ret['in_plan'] = $volume;
				$ret['arategroups'][$balanceId][] = array(
					'name' => $plan->getEntityGroup(),
					'usagev' => $volume,
					'left' => $groupVolumeLeft - $volume,
					'total' => $plan->getGroupVolume($usageType),
					'balance' => $this->balance,
				);
			} else if ($volumeToCharge > 0) {
				$ret['in_group'] = $ret['in_plan'] = $groupVolumeLeft;
				if ($plan->getEntityGroup() !== FALSE && isset($ret['in_group']) && $ret['in_group'] > 0) { // verify that after all calculations we are in group
					$ret['over_group'] = $ret['over_plan'] = $volumeToCharge;
					$ret['arategroups'][$balanceId][] = array(
						'name' => $plan->getEntityGroup(),
						'usagev' => $ret['in_group'],
						'left' => 0,
						'total' => $plan->getGroupVolume($usageType),
						'balance' => $this->balance,
					);
				} else if ($volumeToCharge > 0) {
					$ret['out_group'] = $ret['out_plan'] = $volumeToCharge;
				}
				$services = $this->loadSubscriberServices((isset($row['services']) ? $row['services'] : array()), $row['urt']->sec);
				if ($volumeToCharge > 0 && $this->isRateInServicesGroups($rate, $usageType, $services)) {
					$ret['over_group'] = $ret['over_plan'] = $groupVolumeLeft = $this->usageLeftInServicesGroups($rate, $usageType, $services, $volumeToCharge, $ret['arategroups']);
					$ret['in_plan'] = $ret['in_group'] += $volumeToCharge - $groupVolumeLeft;
					$volumeToCharge = $groupVolumeLeft;
					unset($ret['out_group'], $ret['out_plan']);
				}
			}
		} else {
			$services = $this->loadSubscriberServices((isset($row['services']) ? $row['services'] : array()), $row['urt']->sec);
			if ($this->isRateInServicesGroups($rate, $usageType, $services)) {
				$ret['arategroups'] = array();
				$ret['over_group'] = $ret['over_plan'] = $groupVolumeLeft = $this->usageLeftInServicesGroups($rate, $usageType, $services, $volume, $ret['arategroups']);
				$ret['in_plan'] = $ret['in_group'] = $volume - $groupVolumeLeft;
				$volumeToCharge = $groupVolumeLeft;
			} else { // @todo: else if (dispatcher->isRateInPlugin {dispatcher->trigger->calc}
				$ret['out_plan'] = $ret['out_group'] = $volumeToCharge = $volume;
			}
		}

		$charges = Billrun_Rates_Util::getCharges($rate, $usageType, $volumeToCharge, $plan->getName(), 0); // TODO: handle call offset (set 0 for now)
		Billrun_Factory::dispatcher()->trigger('afterChargesCalculation', array(&$row, &$charges, $this));

		$ret[$this->pricingField] = $charges['total'];
		return $ret;
	}

	/**
	 * load subscribers services objects by their name
	 * 
	 * @param array $services services names
	 * @param int $time unix timestamp of effective datetime
	 * 
	 * @return array of services objects
	 */
	protected function loadSubscriberServices($services, $time) {
		$ret = array();
		foreach ($services as $service) {
			$serviceSettings = array(
				'name' => $service,
				'time' => $time
			);
			$ret[] = new Billrun_Service($serviceSettings);
		}

		return $ret; // array of service objects
	}

	/**
	 * check if rate is includes in customer services groups
	 * 
	 * @param object $rate
	 * @param string $usageType
	 * @param array $services
	 * 
	 * @return boolean true if rate in services groups else false
	 * 
	 * @todo check also if there is available includes in the group (require subscriber balance object)
	 */
	protected function isRateInServicesGroups($rate, $usageType, $services) {
		foreach ($services as $service) {
			if ($service->isRateInEntityGroup($rate, $usageType)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * method to check subset of services if there are groups includes available to use
	 * 
	 * @param array $rate the rate
	 * @param string $usageType usage type
	 * @param array $services array of Billrun_Service objects
	 * @param int $volumeRequired the volume required to charge
	 * @param array $arategroups the group services to return to (reference - will be added to this array)
	 * 
	 * @return int volume left to charge after used by all services groups
	 */
	protected function usageLeftInServicesGroups($rate, $usageType, $services, $volumeRequired, &$arategroups) {
		foreach ($services as $service) {
			if ($volumeRequired <= 0) {
				break;
			}
			
			$serviceGroups = $service->getRateGroups($rate, $usageType);
			foreach ($serviceGroups as $serviceGroup) {
				// pre-check if need to switch to other balance with the new service
				if ($service->isGroupAccountShared($rate, $usageType, $serviceGroup)) {
					$instanceOptions = array_merge($this->row->getRawData(), array('granted_usagev' => $this->granted_volume, 'granted_cost' => $this->granted_cost));
					$instanceOptions['balance_db_refresh'] = true;
					$instanceOptions['sid'] = 0;
					$balance = Billrun_Balance::getInstance($instanceOptions);
				} else {
					$balance = $this->balance;
				}
				$groupVolume = $service->usageLeftInEntityGroup($balance, $rate, $usageType, $serviceGroup);
				if ($groupVolume === FALSE || $groupVolume <= 0) {
					continue;
				}
				if ($volumeRequired <= $groupVolume) {
					$arategroups[(string) $balance->getId()][] = array(
						'name' => $serviceGroup,
						'usagev' => $volumeRequired,
						'left' => $groupVolume - $volumeRequired,
						'total' => $service->getGroupVolume($usageType, $serviceGroup),
						'balance' => $balance,
					);
					return 0;
				}
				$arategroups[(string) $balance->getId()][] = array(
					'name' => $serviceGroup,
					'usagev' => $groupVolume,
					'left' => 0,
					'total' => $service->getGroupVolume($usageType, $serviceGroup),
					'balance' => $balance,
				);
				$volumeRequired -= $groupVolume;
			}
		}
		return $volumeRequired; // volume left to charge
	}

}
