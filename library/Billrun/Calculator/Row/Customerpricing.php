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
	 * @see mongodb update where value equal old value
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

	/**
	 * row service details
	 * 
	 * @param array Array of Billrun_Service objects
	 */
	protected $services = array();

	/**
	 * row services IDs (keys matching $services array)
	 * 
	 * @param array Array of integers
	 */
	protected $servicesIds = array();

	/**
	 * End time of the active billrun (unix timestamp)
	 * @var int
	 */
	protected $activeBillrunEndTime;

	/**
	 * Minimum possible billrun key for newly calculated lines
	 * @var string 
	 */
	protected $activeBillrun;

	/**
	 * Second minimum possible billrun key for newly calculated lines
	 * @var string
	 */
	protected $nextActiveBillrun;

	/**
	 * End time of the next active billrun (unix timestamp)
	 * @var int
	 */
	protected $nextActiveBillrunEndTime;

	/**
	 * current configuration
	 * 
	 * @var type 
	 */
	protected $config = null;
	
	/**
	 * This holds the services used when pricing the row.
	 */
	protected $servicesUsed = array();
	
	protected function init() {
		$this->rate = $this->getRowRate($this->row);
		if ($this->row['sid'] == 0 && $this->row['type'] == 'credit') { // TODO: this is a hack for credit on account level, needs to be fixed in customer calculator
			$this->plan = null;
		} else {
			$planSettings = array(
				'name' => $this->row['plan'],
				'time' => $this->row['urt']->sec,
			);
			$this->plan = Billrun_Factory::plan($planSettings);
		}
		$this->services = [];
		$this->servicesUsed = array();
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
		$volume = $this->usagev;
		$typesWithoutBalance = Billrun_Factory::config()->getConfigValue('customerPricing.calculator.typesWithoutBalance', array('credit', 'flat', 'service'));
		if (in_array($this->row['type'], $typesWithoutBalance)) {
			$charges = Billrun_Rates_Util::getTotalCharge($this->rate, $this->usaget, $volume, $this->row['plan'], $this->getServices(), $this->getCallOffset(), $this->row['urt']->sec);
			$pricingData = array($this->pricingField => $charges);
		} else {
			$pricingData = $this->updateSubscriberBalance($this->usaget, $this->rate);
		}

		if ($pricingData === false) {
			return false;
		}

		if (!$this->isBillable($this->rate)) {
			return $pricingData;
		}

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
		$pricingData = $this->updateBalance($this->rate, $this->plan, $this->usaget, $this->usagev);
		if ($pricingData === false) {
			// failed because of different totals (could be that another server with another line raised the totals). 
			// Need to calculate pricingData from the beginning
			if (++$this->countConcurrentRetries >= $this->concurrentMaxRetries) {
				Billrun_Factory::log()->log('Too many pricing retries for line stamp: ' . $this->row['stamp'], Zend_Log::ALERT);
				return false;
			}
			usleep($this->countConcurrentRetries);
			return $this->updateSubscriberBalance();
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
		if (!is_object($this->plan)) {
			$this->plan = Billrun_Factory::plan(array('name' => $this->plan, 'time' => $this->row['urt']->sec, /* 'disableCache' => true */));
		}
		// we moved the init of plan_ref to customer calc, we leave it here only for verification and avoid b/c issues
		if (!isset($this->row['plan_ref'])) {
			$plan_ref = $this->plan->createRef();
			if (is_null($plan_ref)) {
				Billrun_Factory::log('No plan found for subscriber ' . $this->sid . ', line ' . $this->row['stamp'], Zend_Log::ALERT);
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
			$instanceOptions['orig_sid'] = $this->row['sid'];
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
		if (isset($this->row['realtime']) && $this->row['realtime']) {
			$this->row['balance_ref'] = $this->balance->createRef();
		}
		return true;
	}

	/**
	 * initial the minimum values allowed for finding a balance 
	 */
	protected function initMinBalanceValues() {
		if (empty($this->min_balance_volume) || empty($this->min_balance_volume)) {
			$this->min_balance_volume = abs(Billrun_Factory::config()->getConfigValue('balance.minUsage.' . $this->usaget, Billrun_Factory::config()->getConfigValue('balance.minUsage', 3, 'float'))); // float avoid set type to int
			$this->min_balance_cost = Billrun_Rates_Util::getTotalChargeByRate($this->rate, $this->usaget, $this->min_balance_volume, $this->plan->getName(), $this->getServices(), $this->getCallOffset(), $this->row['urt']->sec);
		}
	}

	/**
	 * gets an array which represents a db ref (includes '$ref' & '$id' keys)
	 * @param array $row
	 */
	public function getRowRate($row) {
		return Billrun_Rates_Util::getRateByRef($row->get('arate', true));
	}

	/**
	 * set the call offset with the value received
	 * 
	 * @param float $val
	 */
	public function setCallOffset($val) {
		$this->call_offset = $val;
	}

	/**
	 * returns the call offset value
	 * 
	 * @return float
	 */
	public function getCallOffset() {
		return $this->call_offset;
	}

	/**
	 * method to update subscriber balance to db
	 * 
	 * @param mixed $rate The rate of associated with the usage.
	 * @param Billrun_Plan $plan the customer plan
	 * @param string $usage_type The type  of the usage
	 * @param int $volume The usage volume (seconds of call, count of SMS, bytes  of data)
	 * 
	 * @return mixed on success update return pricing data array, else false
	 * 
	 */
	public function updateBalance($rate, $plan, $usage_type, $volume) {
		$pricingData = $this->getLinePricingData($volume, $usage_type, $rate, $plan);
		if (isset($this->row['billrun_pretend']) && $this->row['billrun_pretend']) {
			Billrun_Factory::dispatcher()->trigger('afterUpdateSubscriberBalance', array(array_merge($this->row->getRawData(), $pricingData), $this, &$pricingData, $this));
			return $pricingData;
		}
		$balance_id = (string) $this->balance->getId();
		if (!isset($pricingData['arategroups'][$balance_id]) && 
			((isset($pricingData['over_group']) && $pricingData['over_group']) || (isset($pricingData['out_group']) && $pricingData['out_group']))) {
			if (($crashedPricingData = $this->getTx($this->row['stamp'], $this->balance)) !== FALSE) {
				return $crashedPricingData;
			}
			$balancePricingData = $pricingData;
			unset($balancePricingData['arategroups']);
			Billrun_Factory::log("Updating balance " . $balance_id . " of subscriber " . $this->row['sid'], Zend_Log::DEBUG);
			$notInGroupVolume = $balancePricingData['out_group'] ?? ($balancePricingData['over_group'] ?? 0);
			list($query, $update) = $this->balance->buildBalanceUpdateQuery($balancePricingData, $this->row, $notInGroupVolume);
			Billrun_Factory::dispatcher()->trigger('beforeCommitSubscriberBalance', array(&$this->row, &$pricingData, &$query, &$update, $rate, $this));
			$ret = $this->balance->update($query, $update);
			if ($ret === FALSE) {
				Billrun_Factory::log('Update subscriber balance failed on updated existing document.' . PHP_EOL . 'Query: ' . print_R($query, 1) . PHP_EOL . 'Update: ' . print_R($update, 1), Zend_Log::NOTICE);
				return false;
			}
			
			$updatedPricingData = $this->getLineIncludedPricingData($pricingData);
			$volume -= $notInGroupVolume;
			Billrun_Factory::log("Line with stamp " . $this->row['stamp'] . " was written to balance " . $balance_id . " for subscriber " . $this->row['sid'], Zend_Log::DEBUG);
			$this->row['tx_saved'] = true; // indication for transaction existence in balances. Won't & shouldn't be saved to the db.
//			return $pricingData;
		}
			
		if (isset($pricingData['arategroups'])) {
			if (isset($updatedPricingData)) {
				$balancePricingData = array_diff_key($updatedPricingData, array('arategroups' => 'val')); // clone issue
			} else {
				$balancePricingData = array_diff_key($pricingData, array('arategroups' => 'val')); // clone issue
			}
			$pricingData['arategroups'] = $pricingData['arategroups'];
			$arategroups = array(); // will used to flat the structure of pricingData['arategroups'] item
			foreach ($pricingData['arategroups'] as /* $balance_key => */ &$balanceData) {
				$balance = $balanceData[0]['balance'];
				if (($crashedPricingData = $this->getTx($this->row['stamp'], $balance)) !== FALSE) {
					return $crashedPricingData;
				}

				if (!isset($balanceData[0]['balance'])) {
					Billrun_Factory::log("No balance reference on pricing data", Zend_Log::ERR);
					continue;
				}

				foreach ($balanceData as &$data) {
					$data['balance_ref'] = Billrun_Factory::db()->balancesCollection()->createRefByEntity($data['balance']);
					unset($data['balance']);
				}

				$balancePricingData['arategroups'] = $balanceData;

				$balance_id = $balance->getId();
				Billrun_Factory::log("Updating balance " . $balance_id . " of subscriber " . $this->row['sid'], Zend_Log::DEBUG);
				list($query, $update) = $balance->buildBalanceUpdateQuery($balancePricingData, $this->row, $volume);

				Billrun_Factory::dispatcher()->trigger('beforeCommitSubscriberBalance', array(&$this->row, &$balancePricingData, &$query, &$update, $rate, $this));
				$ret = $balance->update($query, $update);
				if ($ret === FALSE) {
					Billrun_Factory::log('Update subscriber balance failed on updated existing document.' . PHP_EOL . 'Query: ' . print_R($query, 1) . PHP_EOL . 'Update: ' . print_R($update, 1), Zend_Log::NOTICE);
					return false;
				}
				Billrun_Factory::log("Line with stamp " . $this->row['stamp'] . " was written to balance " . $balance_id . " for subscriber " . $this->row['sid'], Zend_Log::DEBUG);
				$this->row['tx_saved'] = true; // indication for transaction existence in balances. Won't & shouldn't be saved to the db.
				$arategroups = array_merge($arategroups, $balanceData);
			}
			$pricingData['arategroups'] = $arategroups;
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
                $tx2 = $balance->get('tx2');
                if (is_array($tx2) && empty($tx2)) {
			$balance->set('tx2', new stdClass());
			$balance->save();
		}
		if (!empty($tx) && array_key_exists($stamp, $tx)) { // we're after a crash
			$pricingData = $tx[$stamp]; // restore the pricingData before the crash
			return $pricingData;
		}
		return false;
	}
	
	/**
	 * "clean" pricing data from over group/plan charges and keep only included pricing data.
	 * removes pricing data that is relevant for monthly balance
	 * 
	 * @param array $pricingData
	 * @return array
	 */
	protected function getLineIncludedPricingData($pricingData) {
		$pricingData['aprice'] = 0;
		unset($pricingData['over_group']);
		unset($pricingData['over_plan']);
		return $pricingData;
	}

	/**
	 * Get pricing data for a given rate / subscriber.
	 * 
	 * @param int $volume The usage volume (seconds of call, count of SMS, bytes  of data)
	 * @param string $usageType The type  of the usage (call/sms/data)
	 * @param mixed $rate The rate of associated with the usage.
	 * @param Billrun_Plan $plan the subscriber's current plan
	 * @return array pricing data details of the specific volume
	 * 
	 * @todo refactoring the if-else-if-else-if-else to methods
	 * @todo remove (in/over/out)_plan support (group used instead)
	 */
	protected function getLinePricingData($volume, $usageType, $rate, $plan) {
		$ret = array();
		$balanceId = (string) $this->balance->getId();
		$valueToCharge = $volume;
		$isRetailRate = !isset($this->row['retail_rate']) || $this->row['retail_rate'];
		if ($isRetailRate) { // groups/includes should only be calculated for retail rates (or if fthe flag is not set for backward compatibility)
			if ($plan->isRateInEntityGroup($rate, $usageType)) {
				$groupVolumeLeft = $plan->usageLeftInEntityGroup($this->balance, $rate, $usageType, null, $this->row['urt']->sec);

				$balanceType = key($groupVolumeLeft); // usagev or cost
				$value = current($groupVolumeLeft);
				if ($balanceType == 'cost') {
					$cost = Billrun_Rates_Util::getTotalCharge($rate, $usageType, $volume);
					$valueToCharge = $cost - $value;
				} else {
					$valueToCharge = $volume - $value;
				}

				if ($valueToCharge < 0) {
					$valueToCharge = 0;
					$ret['in_group'] = $ret['in_plan'] = $volume;
					$ret['arategroups'][$balanceId][] = array(
						'name' => $plan->getEntityGroup(),
						$balanceType => $volume,
						'left' => $value - ($balanceType == 'cost' ? $cost : $volume),
						'total' => $plan->getGroupVolume($balanceType == 'cost' ? 'cost' : $usageType, $this->row['aid']),
						'balance' => $this->balance,
					);
				} else if ($valueToCharge >= 0) {
					$ret['in_group'] = $ret['in_plan'] = $value;
					if ($plan->getEntityGroup() !== FALSE && isset($ret['in_group']) && $ret['in_group'] > 0) { // verify that after all calculations we are in group
						$ret['over_group'] = $ret['over_plan'] = $valueToCharge;
						$ret['arategroups'][$balanceId][] = array(
							'name' => $plan->getEntityGroup(),
							$balanceType => $ret['in_group'],
							'left' => 0,
							'total' => $plan->getGroupVolume($balanceType == 'cost' ? 'cost' : $usageType, $this->row['aid']),
							'balance' => $this->balance,
						);
					} else if ($valueToCharge > 0) {
						$ret['out_group'] = $ret['out_plan'] = $valueToCharge;
					}
					$services = $this->getServices();
					if ($valueToCharge > 0 && $this->isRateInServicesGroups($rate, $usageType, $services)) {
						$value = $this->usageLeftInServicesGroups($rate, $usageType, $services, array($balanceType => $valueToCharge), $ret['arategroups']);
						$balanceType = key($value);
						$ret['out_group'] = $ret['out_plan'] = $ret['over_group'] = $ret['over_plan'] = current($value);
						$ret['in_plan'] = $ret['in_group'] += $valueToCharge - $ret['over_group'];
						$valueToCharge = $ret['over_group'];
					}
				}
			} else {
				$balanceType = 'usagev';
				$services = $this->getServices();
				if ($this->isRateInServicesGroups($rate, $usageType, $services)) {
					$ret['arategroups'] = array();
					$groupVolumeLeft = $this->usageLeftInServicesGroups($rate, $usageType, $services, array($balanceType => $volume), $ret['arategroups']);
					$balanceType = key($groupVolumeLeft);
					$ret['over_group'] = $ret['over_plan'] = current($groupVolumeLeft);
					$ret['in_plan'] = $ret['in_group'] = $volume - $ret['over_group'];
					$valueToCharge = $ret['over_group'];
				} else { // @todo: else if (dispatcher->isRateInPlugin {dispatcher->trigger->calc}
					$ret['out_plan'] = $ret['out_group'] = $valueToCharge = $volume;
				}
			}
		}
		
		if ($isRetailRate && $this->isPrepriced()) {
			$prepriced = $this->getLineAprice();
			if ($prepriced === false) {
				return false;
			}
			$charges = (float) $prepriced;
		} else if (empty($balanceType) || $balanceType != 'cost') {
			$charges = Billrun_Rates_Util::getTotalCharge($rate, $usageType, $valueToCharge, $plan->getName(), $this->getServices(), 0, $this->row['urt']->sec); // TODO: handle call offset (set 0 for now)
		} else {
			$charges = $valueToCharge;
		}
		Billrun_Factory::dispatcher()->trigger('afterChargesCalculation', array(&$this->row, &$charges, &$ret, $this));

		$ret[$this->pricingField] = $charges;
		return $ret;
	}
	
	/**
	 * Get this class' cached services property
	 * @return array
	 */
	protected function getServices() {
		if (empty($this->services)) {
			$this->services = $this->loadSubscriberServices((isset($this->row['services_data']) ? $this->row['services_data'] : array()), $this->row['urt']->sec);
		}
		return $this->services;
	}

	/**
	 * load subscribers services objects by their name
	 * 
	 * @param array $services services names
	 * @param int $time unix timestamp of effective datetime
	 * 
	 * @return array of services objects
	 * 
	 * @todo remove backward compatibility of service as string (should be only array)
	 */
	protected function loadSubscriberServices($services, $time) {
		$ret = array();
		$servicesIds = [];
		foreach ($services as $service) {
			$serviceId = isset($service['service_id']) ? $service['service_id'] : 0;
			$serviceName = isset($service['name']) ? $service['name'] : $service;
			$serviceSettings = array(
				'name' => $serviceName,
				'time' => $time,
				'disableCache' => true,
				'plan_included' => isset($service['plan_included']) ? $service['plan_included'] : false,
			);
			
			if (isset($service['from']->sec)) {
				$serviceSettings['service_start_date'] = $service['from']->sec;
			}
			
			if (!($serviceObject = Billrun_Factory::service($serviceSettings))) {
				continue;
			}
			
			if (isset($service['from']) && $serviceObject->isExhausted($service['from'], $time)) {
				continue;
			}
			
			$servicePeriod = $serviceObject->get("balance_period");
			if ($servicePeriod && $servicePeriod !== "default" && isset($service['to']->sec)) {
				$sortKey = (int) $service['to']->sec;
			} else {
				$sortKey = (int) Billrun_Billingcycle::getEndTime(Billrun_Billingcycle::getBillrunKeyByTimestamp($time)); // end of cycle
			}
			
			while (isset($ret[$sortKey])) { // in case service with same expiration
				++$sortKey;
			}
			
			$ret[$sortKey] = $serviceObject;
			$servicesIds[$sortKey] = $serviceId;
		}
		
		ksort($ret);
		ksort($servicesIds);
		$this->servicesIds = array_values($servicesIds);

		return array_values($ret); // array of service objects
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
	 * @param int $required the volume/cost required to charge
	 * @param array $arategroups the group services to return to (reference - will be added to this array)
	 * 
	 * @return int volume left to charge after used by all services groups
	 */
	protected function usageLeftInServicesGroups($rate, $usageType, $services, $required, &$arategroups) {
		$keyRequired = key($required);
		$valueRequired = current($required);
		foreach ($services as $key => $service) {
			if ($valueRequired < 0) {
				break;
			}
			
			$serviceName = $service->getName();
			$serviceQuantity = 1;
			$serviceGroups = $service->getRateGroups($rate, $usageType);
			$aid = $this->row->getRawData()['aid'];
			foreach ($serviceGroups as $serviceGroup) {
				$serviceSettings = array(
					'service_name' => $serviceName,
					'service_id' => $this->servicesIds[$key],
					'balance_period' => ((!empty($balance_period = $service->get('balance_period'))) ? $balance_period : 'default'),
					'service_start_date' => $service->get('service_start_date'),
				);
				$isGroupShared = $service->isGroupAccountShared($rate, $usageType, $serviceGroup);
				$isGroupQuantityAffected = $service->isGroupQuantityAffected($serviceGroup);
				// pre-check if need to switch to other balance with the new service
				if ($isGroupShared && $this->balance['sid'] != 0) { // if need to switch to shared balance (from plan)
					$instanceOptions = array_merge($this->row->getRawData(), array('granted_usagev' => $this->granted_volume, 'granted_cost' => $this->granted_cost), $serviceSettings);
					$instanceOptions['balance_db_refresh'] = true;
					$instanceOptions['sid'] = 0;
					$instanceOptions['orig_sid'] = $this->row['sid'];
					$balance = Billrun_Balance::getInstance($instanceOptions);
				} else if ($this->balance['sid'] == 0) { // if need to switch to non-shared balance (from plan)
					$instanceOptions = array_merge($this->row->getRawData(), array('granted_usagev' => $this->granted_volume, 'granted_cost' => $this->granted_cost), $serviceSettings);
					$instanceOptions['balance_db_refresh'] = true;
					$instanceOptions['sid'] = $this->row['sid'];
					$balance = Billrun_Balance::getInstance($instanceOptions);
				} else if ($serviceSettings['balance_period'] != 'default') { // cannot use plan balance as this is custom period balance (different from and/or to)
					$instanceOptions = array_merge($this->row->getRawData(), array('granted_usagev' => $this->granted_volume, 'granted_cost' => $this->granted_cost), $serviceSettings);
					$instanceOptions['balance_db_refresh'] = true;
					$instanceOptions['sid'] = $this->row['sid'];
					$balance = Billrun_Balance::getInstance($instanceOptions);
				} else if (!$service->get('plan_included')) {
					$instanceOptions = array_merge($this->row->getRawData(), array('granted_usagev' => $this->granted_volume, 'granted_cost' => $this->granted_cost), $serviceSettings);
					$instanceOptions['balance_db_refresh'] = true;
					$instanceOptions['sid'] = $this->row['sid'];
					$instanceOptions['add_on'] = true;
					$balance = Billrun_Balance::getInstance($instanceOptions);
				} else { // use same balance as plan balance
					$balance = $this->balance;
				}
				if (!$isGroupShared || ($isGroupShared && $service->isGroupAccountPool($serviceGroup))) {
					$serviceQuantity = $this->getServiceQuantity($this->row['services_data'], $serviceName);
				}
				$serviceMaximumQuantity = 1;
				if($isGroupShared && !$service->isGroupAccountPool($serviceGroup) && $isGroupQuantityAffected) {
					$serviceMaximumQuantity = $service->getServiceMaximumQuantityByAid($aid, $this->row['urt']->sec);
				}
				
				$groupVolume = $service->usageLeftInEntityGroup($balance, $rate, $usageType, $serviceGroup, $this->row['urt']->sec, $serviceQuantity, $serviceMaximumQuantity);
				$balanceType = key($groupVolume); // usagev or cost
				$value = current($groupVolume);

				if ($value === FALSE || $value <= 0) {
					continue;
				}
				if ($balanceType != $keyRequired) {
					if ($keyRequired == 'cost') {
						$comparedValue = Billrun_Rates_Util::getTotalCharge($rate, $usageType, $value, $this->row['plan'], $services);
					} else {
						$comparedValue = Billrun_Rates_Util::getVolumeByRate($rate, $usageType, $value, $this->row['plan'], $services, 0, 0, 0, null, $this->row['usagev']);
					}
				} else {
					$comparedValue = $value;
				}
				if ($valueRequired <= $comparedValue) {
					$arategroups[(string) $balance->getId()][] = array(
						'name' => $serviceGroup,
						$balanceType => $valueRequired,
						'left' => $value - $valueRequired,
						'total' => $service->getGroupVolume($balanceType == 'cost' ? 'cost' : $usageType, $this->row['aid'], $serviceGroup, null, $serviceQuantity, $serviceMaximumQuantity),
						'balance' => $balance,
					);
					$this->servicesUsed[] = $service;
					return array($keyRequired => 0);
				}
				$arategroups[(string) $balance->getId()][] = array(
					'name' => $serviceGroup,
					$balanceType => $value,
					'left' => 0,
					'total' => $service->getGroupVolume($balanceType == 'cost' ? 'cost' : $usageType, $this->row['aid'], $serviceGroup, null, $serviceQuantity, $serviceMaximumQuantity),
					'balance' => $balance,
				);
				if ($keyRequired != $balanceType) {
					$valueRequired -= $comparedValue;
				} else {
					$valueRequired -= $value;
				}
				$this->servicesUsed[] = $service;
			}
		}
		return array($keyRequired => $valueRequired); // volume/cost left to charge
	}

	/**
	 * a trigger that occurs before the balance update is done (and precedents calculations).
	 * allows to add more logic before the update
	 * 
	 * @return boolean
	 */
	public function preUpdate() {
		if (!isset($this->row['realtime']) || !$this->row['realtime']) {
			return false;
		}
		if (!isset($this->row['usagev_offset'])) {
			$this->row['usagev_offset'] = $this->getRowCurrentUsagev();
		}

		if ($this->isRebalanceRequired()) {
			$this->rebalance();
		}
		return true;
	}

	/**
	 * gets the current usagev used so far
	 * 
	 * @return float
	 */
	protected function getRowCurrentUsagev() {
		try {
			if ($this->isPostpayChargeRequest()) {
				return 0;
			}
			$lines_coll = Billrun_Factory::db()->linesCollection();
			$query = $this->getRowCurrentUsagevQuery();
			$line = current(iterator_to_array($lines_coll->aggregate($query)));
		} catch (Exception $ex) {
			Billrun_Factory::log($ex->getCode() . ': ' . $ex->getMessage());
		}
		return isset($line['sum']) ? $line['sum'] : 0;
	}

	/**
	 * gets the current usagev query (for getRowCurrentUsagev function)
	 * 
	 * @return array
	 */
	protected function getRowCurrentUsagevQuery() {
		$query = array(
			array(
				'$match' => array(
					"sid" => $this->row['sid'],
					"session_id" => $this->row['session_id'],
				)
			),
			array(
				'$group' => array(
					'_id' => null,
					'sum' => array('$sum' => '$usagev'),
				)
			)
		);
		return $query;
	}

	/**
	 * checks whether the request is a  one time charge requests
	 * 
	 * @return boolean
	 */
	protected function isPostpayChargeRequest() {
		return $this->row['request_type'] == Billrun_Factory::config()->getConfigValue('realtimeevent.requestType.POSTPAY_CHARGE_REQUEST');
	}

	/**
	 * checks if the line needs to run the rebalance mechanism
	 * 
	 * @return boolean
	 */
	protected function isRebalanceRequired() {
		if ($this->isPostpayChargeRequest()) {
			return false;
		}
		if ($this->isReblanceOnLastRequestOnly()) {
			$rebalanceTypes = array('final_request');
		} else {
			$rebalanceTypes = array('final_request', 'update_request');
		}
		return ($this->row['realtime'] && in_array($this->row['record_type'], $rebalanceTypes));
	}

	/**
	 * checks whether the rebalance should occur on every request or only at the last (final) request
	 * 
	 * @return boolean
	 */
	protected function isReblanceOnLastRequestOnly() {
		$config = $this->getConfig($this->row);
		return (isset($config['realtime']['rebalance_on_final']) && $config['realtime']['rebalance_on_final']);
	}

	/**
	 * gets the current configuration according to the file type
	 * 
	 * @return array
	 */
	protected function getConfig() {
		if (empty($this->config)) {
			$this->config = Billrun_Factory::config()->getFileTypeSettings($this->row['type'], true);
		}
		return $this->config;
	}

	/**
	 * make a rebalance to the row
	 * 
	 */
	protected function rebalance() {
		$lineToRebalance = $this->getLineToUpdate()->current();
		$usagev = $this->getRealUsagev($lineToRebalance);
		$unit = isset($this->row['usagev_unit']) ? $this->row['usagev_unit'] : 'counter';
		$realUsagev = Billrun_Utils_Units::convertVolumeUnits($usagev, $this->row['usaget'], $unit, true);
		$chargedUsagev = $this->getChargedUsagev($lineToRebalance);
		if ($chargedUsagev !== null) {
			$rebalanceUsagev = $realUsagev - $chargedUsagev;
			if (($rebalanceUsagev) < 0) {
				$this->handleRebalanceRequired($rebalanceUsagev, $realUsagev, $lineToRebalance, $this->row);
			}
		}
	}

	/**
	 * Gets the Line that needs to be updated (on rebalance) from archive collection
	 */
	protected function getLineToUpdate() {
		$lines_archive_coll = Billrun_Factory::db()->archiveCollection();
		$findQuery = array(
			"sid" => $this->row['sid'],
			"session_id" => $this->row['session_id'],
		);
		$sort = array(
			'sid' => 1,
			'session_id' => 1,
			'_id' => -1,
		);
		$line = $lines_archive_coll->query($findQuery)->cursor()->sort($sort)->limit(1);
		return $line;
	}

	/**
	 * Gets the real usagev of the user (known only on the next API call)
	 * 
	 * @return float
	 */
	protected function getRealUsagev($lineToRebalance) {
		$config = $this->getConfig();
		$usagev = 0;
		$usedUsagevFields = is_array($config['realtime']['used_usagev_field']) ? $config['realtime']['used_usagev_field'] : array($config['realtime']['used_usagev_field']);
		foreach ($usedUsagevFields as $usedUsagevField) {
			$usedUsage = Billrun_util::getIn($this->row['uf'], $usedUsagevField);
			$usagev += !is_null($usedUsage) ? $usedUsage : 0;
		}

		$this->handleAccumulativeUsagev($usagev, $lineToRebalance, $config);
		return $usagev;
	}

	protected function handleAccumulativeUsagev(&$usagev, $lineToRebalance, $config) {
		if (Billrun_Util::getIn($config, array('realtime', 'used_usagev_accumulative'), false)) {
			$this->row['accumulative_usagev'] = $usagev;
			$prevAccumulativeUsagev = Billrun_Util::getIn($lineToRebalance, 'accumulative_usagev', 0);
			$usagev -= $prevAccumulativeUsagev;
			$this->row['usagev_delta'] = $this->row['accumulative_usagev'] - $prevAccumulativeUsagev;
		}
	}

	/**
	 * Gets the amount of usagev that was charged
	 * 
	 * @return flaot
	 */
	protected function getChargedUsagev($lineToRebalance) {
		if ($this->isReblanceOnLastRequestOnly()) {
			$lines_archive_coll = Billrun_Factory::db()->archiveCollection();
			$query = $this->getRebalanceQuery($this->row);
			if (!$query) {
				return 0;
			}
			$line = $lines_archive_coll->aggregate($query)->current();
			return $line['sum'];
		}
		return $lineToRebalance['usagev'];
	}

	/**
	 * gets the query used in getChargedUsagev
	 * 
	 * @param type $lineToRebalance
	 * @return array
	 */
	protected function getRebalanceQuery($lineToRebalance) {
		$sessionQuery = $this->getSessionIdQuery($lineToRebalance->getRawData());
		if (!$sessionQuery) {
			Billrun_Factory::log('Customerpricing getSessionIdQuery - cannot find previous lines. details: ' . print_R($lineToRebalance, 1));
			return false;
		}
		$findQuery = array_merge(array("sid" => $lineToRebalance['sid']), $sessionQuery);
		return array(
			array(
				'$match' => $findQuery
			),
			array(
				'$group' => array(
					'_id' => 'sid',
					'sum' => array('$sum' => '$usagev')
				)
			)
		);
	}

	/**
	 * gets a query which represents the session id of the row (to find previous lines that are related to the current line)
	 * 
	 * @param array $row
	 * @return array
	 */
	protected function getSessionIdQuery($row) {
		if (isset($row['session_id'])) {
			return array('session_id' => $row['session_id']);
		}
		return false;
	}

	/**
	 * handles the rebalance it self
	 * 
	 * @param type $rebalanceUsagev
	 * @param type $realUsagev
	 * @param type $lineToRebalance
	 * @param type $originalRow
	 * @return boolean
	 */
	protected function handleRebalanceRequired($rebalanceUsagev, $realUsagev, $lineToRebalance, $originalRow) {
		return true;
	}

	/**
	 * gets the data required for the rebalance
	 * 
	 * @param array $lineToRebalance
	 * @param array $rate
	 * @param float $rebalanceUsagev
	 * @param float $realUsagev
	 * @param string $usaget
	 * @return array
	 */
	protected function getRebalanceData($lineToRebalance, $rate, $rebalanceUsagev, $realUsagev, $usaget) {
		$rebalancePricingData = $this->getLinePricingData($realUsagev, $usaget, $rate, $this->plan);
		$rebalanceData = array(
			'usagev' => $rebalanceUsagev,
			'aprice' => $lineToRebalance['aprice'] - $rebalancePricingData['aprice'],
		);

		foreach ($rebalancePricingData as $rebalanceKey => $rebalanceVal) {
			if ($rebalanceKey === 'arategroup') {
				continue;
			}
			$rebalanceData[$rebalanceKey] = $rebalanceVal - $lineToRebalance[$rebalanceKey];
		}

		return $rebalanceData;
	}

	/**
	 * return whether we need to consider intervals when rebalancing usagev balance
	 * 
	 * @return boolean
	 * @todo move hard-coded values to configuration
	 */
	protected function needToRebalanceUsagev() {
		return ($this->row['realtime'] && $this->row['record_type'] === 'final_request');
	}

	/**
	 * Gets the update query to update subscriber's Line
	 * 
	 * @param array $rebalanceData
	 * @return array
	 * @todo We need to update usagevc, in_plan, out_plan, in_group, usagesb
	 */
	protected function getUpdateLineUpdateQuery($rebalanceData) {
		unset($rebalanceData['billrun']);
		$ret = array('$inc' => $rebalanceData);
		foreach ($rebalanceData as $rebalanceKey => $rebalanceValue) {
			$ret['$inc']['rebalance_' . $rebalanceKey] = $rebalanceValue;
		}
		return $ret;
	}

	public function triggerEvents($balanceBefore) {
		
	}

	
	
	//=======================================
	public function getBalance() {
		return $this->balance;
	}
	
	public function getPlan() {
		return $this->plan;
	}
	
	public function getUsedServices() {
		return $this->servicesUsed;
	}

	/**
	 * Get the prepriced value received in the CDR
	 * 
	 * @param type $userFields
	 * @return aprice if the field found, false otherwise
	 */
	protected function getLineAprice() {
		$userFields = $this->row['uf'];
		$usageType = $this->row['usaget'];
		$prepricedMapping = Billrun_Factory::config()->getFileTypeSettings($this->row['type'], true)['pricing'];
		$apriceField = isset($prepricedMapping[$usageType]['aprice_field']) ? $prepricedMapping[$usageType]['aprice_field'] : null;
		$aprice = Billrun_util::getIn($userFields, $apriceField);
		if (!is_null($aprice) && is_numeric($aprice)) {
			$apriceMult = isset($prepricedMapping[$usageType]['aprice_mult']) ? $prepricedMapping[$usageType]['aprice_mult'] : null;
			if (!is_null($apriceMult) && is_numeric($apriceMult)) {
				$aprice *= $apriceMult;
			}
			if(Billrun_Calculator_Tax::isLinePreTaxed($this->row)) {
				$aprice = Billrun_Calculator::getInstance(['type'=>'tax'])->removeTax($aprice, $this->row);
			}
			return $aprice;
		}
		
		Billrun_Factory::log('Price field "' . $apriceField . '" is missing or invalid for line ' . $this->row['stamp'] . ', file ' . $this->row['file'], Zend_Log::ALERT);
		return false;
	}
	
	/**
	* method to define if row is pre-priced
	* 
	* @return boolean true if prepriced else false
	*/
	public function isPrepriced() {
		return isset($this->row['prepriced']) ? $this->row['prepriced'] : false;
	}

	protected function getServiceQuantity($servicesData = array(), $serviceName) {
		$quantity = 1;
		foreach ($servicesData as $service) {
			if ($service['name'] == $serviceName && !empty($service['quantity'])) {
				$quantity = intval($service['quantity']);
			}
		}
		return $quantity;
	}

}
