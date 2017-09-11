<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2017 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Roaming Packages plugin for roaming packages.
 *
 * @package  Application
 * @subpackage Plugins
 * @since    2.8
 */
class roamingPackagesPlugin extends Billrun_Plugin_BillrunPluginBase {
	
	/**
	 * time of the current row - unix timestamp.
	 * 
	 * @var int
	 */
	protected $lineTime = null;
	
	/**
	 * current row usaget.
	 * 
	 * @var string
	 */
	protected $lineType = null;
	
	/**
	 * the roaming packages names.
	 * 
	 * @var array
	 */
	protected $roamingPackages;
	

	protected $package = null;
	
	/**
	 * @var Mongodloid_Collection 
	 */
	protected $balances = null;
	
	
	protected $ownedPackages = null; 
	
	
	/**
	 * balances that are full with no more usage left.
	 * 
	 * @var array
	 */
	protected $exhaustedBalances = array();
	
	/**
	 * The balance to update.
	 * 
	 * @var Mongodloid_Entity
	 */
	protected $balanceToUpdate = null;
	
	/**
	 * usage to update the matched balance.
	 * 
	 * @var int
	 */
	protected $extraUsage; 
	
	
	/**
	 * inspect loops in the flow of updating roaming balance
	 * 
	 * @var int
	 */
	protected $countConcurrentRetries = 0;
	
	/**
	 * max retries on concurrent balance updates loops
	 * 
	 * @var int
	 */
	protected $concurrentMaxRetries;
	
	protected $row;
	
	protected $plan;
	
	protected $coefficient;
	
	
	
	public function __construct() {
		$this->roamingPackages = Billrun_Factory::config()->getConfigValue('roamingPackages.available_packages');
		$this->balances = Billrun_Factory::db(array('name' => 'balances'))->balancesCollection()->setReadPreference('RP_PRIMARY');
		$this->concurrentMaxRetries = (int) Billrun_Factory::config()->getConfigValue('updateValueEqualOldValueMaxRetries', 8);
	}
	
	public function beforeUpdateSubscriberBalance($balance, $row, $rate, $calculator) {
		if (($row['type'] == 'nrtrde' && in_array($row['usaget'], array('call', 'incoming_call'))) || $row['type'] == 'ggsn' || isset($row['roaming'])) {
			if (isset($row['urt'])) {
				$this->lineTime = $row['urt']->sec;
				$this->lineType = $row['type'];
				$this->extraUsage = $row['usagev'];
				$this->coefficient = 1;
			} else {
				Billrun_Factory::log()->log('urt wasn\'t found for line ' . $row['stamp'] . '.', Zend_Log::ALERT);
			}
	
			if ($row['usaget'] == 'sms') {
				$this->coefficient = $this->coefficient * 60;
				$this->extraUsage = $row['usagev'] * $this->coefficient;
			}			
			$this->row = $row;
		}
		
		$this->ownedPackages = !empty($row['packages']) ? $row['packages'] : array();
		$this->exhaustedBalances = array();
		$this->balanceToUpdate = null;
		$this->package = null;
		$this->plan = null;
	}
	
	public function beforeCommitSubscriberBalance(&$row, &$pricingData, &$query, &$update, $arate, $calculator) {
		if (!is_null($this->package) && ((($row['type'] == 'nrtrde' && in_array($row['usaget'], array('call', 'incoming_call'))) || $row['type'] == 'ggsn') || isset($row['roaming']))) {
			Billrun_Factory::log()->log("Updating balance " . $this->balanceToUpdate['billrun_month'] . " of subscriber " . $row['sid'], Zend_Log::DEBUG);
			$row['roaming_package'] = $this->package;
			$balancesIncludeRow = array();
			$roamingUpdate = array();
			if (!is_null($this->balanceToUpdate)) {
				$packageLimits = $this->getPackageJoinedValues($this->balanceToUpdate['service_name'], $this->plan);
				if (!empty($packageLimits)) {
					$joinedField = $packageLimits['joined_field'];
					$joinedUsageTypes = $packageLimits['joined_usage_types'];
				}
				$roamingQuery = array(
					'sid' => $row['sid'],
					'billrun_month' => $this->balanceToUpdate['billrun_month'],
					'tx' . $row['stamp'] => array('$exists' => false)
				);
				
				$roamingUpdate['$inc']['balance.totals.' . $row['usaget'] . '.usagev'] = floor($this->extraUsage / $this->coefficient);
				$roamingUpdate['$set']['balance.totals.' . $row['usaget'] . '.cost'] = 0;
				$roamingUpdate['$inc']['balance.totals.' . $row['usaget'] . '.count'] = 1;
				$roamingUpdate['$set']['tx'][$row['stamp']] = array('package' => $this->package, 'usaget' => $row['usaget'], 'usagev' => floor($this->extraUsage / $this->coefficient));
				if (!empty($joinedField) && in_array($row['usaget'], $joinedUsageTypes)) {
					$roamingUpdate['$inc']['balance.totals.' . $joinedField . '.usagev'] = $this->extraUsage;
					$roamingUpdate['$inc']['balance.totals.' . $joinedField . '.count'] = 1;
				}
		
				$this->balances->update($roamingQuery, $roamingUpdate, array('w' => 1));
				$balancesIncludeRow[] = array(
					'service_name' => $this->balanceToUpdate['service_name'],
					'package_id' =>  $this->balanceToUpdate['service_id'],
					'billrun_month' => $this->balanceToUpdate['billrun_month'], 
					'usage_before' => array(
						'call' => $this->balanceToUpdate['balance']['totals']['call']['usagev'],
						'incoming_call' => $this->balanceToUpdate['balance']['totals']['incoming_call']['usagev'],
						'sms' => $this->balanceToUpdate['balance']['totals']['sms']['usagev'], 
						'data' => $this->balanceToUpdate['balance']['totals']['data']['usagev']
					)
				);
			}
			
			if (!empty($this->exhaustedBalances)) {
				$exhaustedUpdate = array();
				foreach ($this->exhaustedBalances as $exhausted) {
					$exhaustedBalance = $exhausted['balance']->getRawData();
					$packageLimits = $this->getPackageJoinedValues($exhaustedBalance['service_name'], $this->plan);
					$usageLeft = floor($exhausted['usage_left'] / $this->coefficient);
					$exhaustedBalancesKeys[] = array(
						'service_name' => $exhaustedBalance['service_name'],
						'package_id' =>  $exhaustedBalance['service_id'],
						'billrun_month' => $exhaustedBalance['billrun_month'], 
						'usage_before' => array(
							'call' => $exhaustedBalance['balance']['totals']['call']['usagev'],
							'incoming_call' => $exhaustedBalance['balance']['totals']['incoming_call']['usagev'], 
							'sms' => $exhaustedBalance['balance']['totals']['sms']['usagev'], 
							'data' => $exhaustedBalance['balance']['totals']['data']['usagev']
						)
					);
					$exhaustedUpdate['$inc']['balance.totals.' . $row['usaget'] . '.usagev'] = $usageLeft;
					$exhaustedUpdate['$set']['balance.totals.' . $row['usaget'] . '.cost'] = 0;
					$exhaustedUpdate['$inc']['balance.totals.' . $row['usaget'] . '.count'] = 1;
					$exhaustedUpdate['$set']['balance.totals.' . $row['usaget'] . '.exhausted'] = true;	
					$exhaustedUpdate['$set']['tx'][$row['stamp']] = array('package' => $exhaustedBalance['service_name'], 'usaget' => $row['usaget'], 'usagev' => $usageLeft);
					if (!empty($packageLimits['joined_field']) && in_array($row['usaget'], $packageLimits['joined_usage_types'])) {
						$exhaustedUpdate['$inc']['balance.totals.' . $packageLimits['joined_field'] . '.usagev'] = $exhausted['usage_left'];
						$exhaustedUpdate['$inc']['balance.totals.' . $packageLimits['joined_field'] . '.count'] = 1;
						$exhaustedUpdate['$set']['balance.totals.' . $packageLimits['joined_field'] . '.exhausted'] = true;

					}
					$this->balances->update(array('_id' => $exhaustedBalance['_id']), $exhaustedUpdate);	
				}
			}
		

			if (isset($exhaustedBalancesKeys)) {
				$balancesIncludeRow = array_merge($balancesIncludeRow, $exhaustedBalancesKeys);
			}
			if ((isset($balancesIncludeRow))) {
				$row['roaming_balances'] = $balancesIncludeRow;
			}
		}
	}
	
	public function afterUpdateSubscriberBalance($row, $balance, &$pricingData, $calculator) {
		if (!is_null($this->package) && ((($row['type'] == 'nrtrde' && in_array($row['usaget'], array('call', 'incoming_call'))) || $row['type'] == 'ggsn') || isset($row['roaming']))) {	
			$this->removeRoamingBalanceTx($row);
		}
	}
	
	/**
	 * method to override the plan group limits
	 * 
	 * @param type $rateUsageIncluded
	 * @param type $groupSelected
	 * @param type $limits
	 * @param type $plan
	 * @param type $usageType
	 * @param type $rate
	 * @param type $subscriberBalance
	 * 
	 */
	public function planGroupRule(&$rateUsageIncluded, &$groupSelected, $limits, $plan, $usageType, $rate, $subscriberBalance) {
		if (!in_array($groupSelected, $this->roamingPackages) || !isset($this->lineType)) {
			return;
		}

		$matchedPackages = array_filter($this->ownedPackages, function($package) use ($usageType, $rate) {
			return in_array($package['service_name'], $rate['rates'][$usageType]['groups']);
		});
		if (empty($matchedPackages)) {
			$groupSelected = FALSE;
			return;
		}
		$this->plan = $plan;
		$UsageIncluded = 0;
		$subscriberSpent = 0;
		foreach ($matchedPackages as $package) {
			$from = strtotime($package['from_date']);
			$to = strtotime($package['to_date']);
			if (!($this->lineTime >= $from && $this->lineTime <= $to)) {
				continue;
			}
			$usageType = $this->getTransformedUsageType($package['service_name'], $plan, $usageType);
			$billrunKey = $package['service_name'] . '_' . date("Ymd", $from) . '_' . date("Ymd", $to) . '_' . $package['id'];
			$this->createRoamingPackageBalanceForSid($subscriberBalance, $billrunKey, $plan, $from, $to, $package['id'], $package['service_name'], $package['balance_priority']);
		}
		
		$roamingQuery = array(
			'sid' => $subscriberBalance['sid'],
			'$and' => array(
				array('to' => array('$exists' => true)),
				array('to' => array('$gte' => new MongoDate($this->lineTime))),
				array('from' => array('$exists' => true)),
				array('from' => array('$lte' => new MongoDate($this->lineTime)))
			),
			'$or' => array(
				array('balance.totals.' . $usageType . '.exhausted' => array('$exists' => false)),
				array('balance.totals.' . $usageType . '.exhausted' => array('$ne' => true)),
			),
		);
		$roamingBalances = $this->balances->query($roamingQuery)->cursor()->sort(array('balance_priority' => 1));
		if ($roamingBalances->current()->isEmpty()) {
			Billrun_Factory::log()->log("Didn't found roaming balance for sid:" . $subscriberBalance['sid'] . ' row stamp:' . $this->row['stamp'], Zend_Log::NOTICE);
		}
		foreach ($roamingBalances as $balance) {
			$balancePackage = $balance['service_name'];
			$usageType = $this->getTransformedUsageType($balancePackage, $plan, $this->row['usaget']);
			if (!isset($plan->get('include.groups.' . $balancePackage)[$usageType])) {
				continue;
			}
			$subRaw = $balance->getRawData();
			$stamp = strval($this->row['stamp']);
			$txValue = isset($subRaw['tx']) && array_key_exists($stamp, $subRaw['tx']) ? $subRaw['tx']['stamp'][$usageType] : 0;
			$UsageIncluded += (int) $plan->get('include.groups.' . $balancePackage)[$usageType];
			if (isset($balance['balance']['totals'][$usageType])) {
				$subscriberSpent += $balance['balance']['totals'][$usageType]['usagev'] - $txValue;
				$usageLeft = (int) $plan->get('include.groups.' . $balancePackage)[$usageType] - $balance['balance']['totals'][$usageType]['usagev'];
				$volume = $usageLeft - $this->extraUsage;
				$subscriberBalance->__set('balance.groups.' . $balancePackage . '.' . $this->row['usaget'] . '.usagev', ceil($subscriberSpent / $this->coefficient));
				$groupSelected = $balancePackage;
				$this->package = $balancePackage;
				if ($volume > 0) {
					$this->balanceToUpdate = $balance;
					break;
				} else {
					array_push($this->exhaustedBalances, array('balance' => $balance, 'usage_left' => $usageLeft));
					$this->extraUsage = -($volume);
				}
			}
		}

		$roundedUsage = floor($UsageIncluded / $this->coefficient);
		if (!empty($UsageIncluded) && $roundedUsage > 0) {
			$rateUsageIncluded = $roundedUsage;
		} else {
			$rateUsageIncluded = 0;
		}
		
		if(!floor($roundedUsage - $subscriberBalance['balance']['groups'][$groupSelected][$this->row['usaget']]['usagev'])) {
			$this->balanceToUpdate = null;
			$this->exhaustedBalances = array();
		}

	}
	
	/**
	 * Creates balance for roaming packages.
	 * 
	 * @param type $subscriberBalance - the "regular" balance of the subscriber.
	 * @param type String $billrunKey - the billrun_month of the balance
	 * @param type $plan - subscriber's plan.
	 * @param type $from - unixtimestamp representing the starting date of the package.
	 * @param type $to - unixtimestamp representing the end date of the package.
	 * 
	 */
	protected function createRoamingPackageBalanceForSid($subscriberBalance, $billrunKey, $plan, $from, $to, $serviceId, $serviceName, $balancePriority) {
		$planRef = $plan->createRef();
		$packageLimits = $this->getPackageJoinedValues($serviceName, $plan);
		if (!empty($packageLimits)) {
			Billrun_Balance::createBalanceIfMissing($subscriberBalance['aid'], $subscriberBalance['sid'], $billrunKey, $planRef, $from, $to, $serviceId, $serviceName, $balancePriority, $packageLimits['joined_field']);
		} else {
			Billrun_Balance::createBalanceIfMissing($subscriberBalance['aid'], $subscriberBalance['sid'], $billrunKey, $planRef, $from, $to, $serviceId, $serviceName, $balancePriority);
		}
	}

	/**
	 * removes the transactions from the subscriber's roaming balance to save space.
	 * @param type $row
	 */
	protected function removeRoamingBalanceTx($row){
		$ids = array();
		if (!is_null($this->balanceToUpdate)) {
			array_push($ids, $this->balanceToUpdate->getRawData()['_id']);
		}
		
		if (!empty($this->exhaustedBalances)) {
			foreach ($this->exhaustedBalances as $exhausted) {
				$balance = $exhausted['balance'];
				array_push($ids, $balance->getRawData()['_id']);
			}			
		}
		
		$query = array(
			'_id' => array('$in' => $ids),
		);
		$update = array(
			'$unset' => array(
				'tx.' . $row['stamp'] => 1
			)
		);
		$this->balances->update($query, $update);
	}
	
	/**
	 * Returns usage type with considiration to joined usage types.
	 * 
	 * @param type String $serviceName - the package service name.
	 * @param type String $plan - billrun plan.
	 * @param type String $usaget - row usage type.
	 * 
	 * @return String - The correct usage type.
	 */
	protected function getTransformedUsageType($serviceName, $plan, $usaget) {
		$usageType = $usaget;
		$packageLimits = $this->getPackageJoinedValues($serviceName, $plan);
		if (isset($packageLimits['joined_usage_types']) && isset($packageLimits['joined_field'])) {
			$packageJoinedTypes = $packageLimits['joined_usage_types'];
			$packageJoinedField = $packageLimits['joined_field'];
			if (in_array($usageType, $packageJoinedTypes)) {
				$usageType = $packageJoinedField;
			}
		}
		return $usageType;
	}
	
	/**
	 * Returns information on package joined fields.
	 * 
	 * @param type String $serviceName - the package service name.
	 * @param type String $plan - billrun plan.
	 * 
	 * @return array - The package limits and false if doesn't exists.
	 */
	protected function getPackageJoinedValues($serviceName, $plan) {
		$packageLimits = $plan->get('include.groups.' . $serviceName. '.limits');
		if (!empty($packageLimits)) {
			return $packageLimits;
		}
		return false;
	}
}
