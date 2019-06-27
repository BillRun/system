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

	/**
	 * usage need to subtract from the balances in case of rebalance.
	 * 
	 * @var array
	 */
	protected $rebalanceUsageSubtract = array(); 


	public function __construct() {
		$this->balances = Billrun_Factory::db(array('name' => 'balances'))->balancesCollection()->setReadPreference('RP_PRIMARY');
		$this->concurrentMaxRetries = (int) Billrun_Factory::config()->getConfigValue('updateValueEqualOldValueMaxRetries', 8);
	}
	
	public function beforeUpdateSubscriberBalance($balance, $row, $rate, $calculator) {
		if ($row['type'] == 'tap3' && in_array($row['usaget'], array('call', 'data', 'incoming_call')) || isset($row['roaming'])) {
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
	
	public function beforeCommitSubscriberBalance(&$row, &$pricingData, &$query, &$update, $arate, $calculator){
		if (!is_null($this->package) && (($row['type'] == 'tap3' && in_array($row['usaget'], array('call', 'data', 'incoming_call'))) || isset($row['roaming']))) {
			Billrun_Factory::log()->log("Updating balance " . $this->balanceToUpdate['billrun_month'] . " of subscriber " . $row['sid'], Zend_Log::DEBUG);
			$row['roaming_package'] = $this->package;
			$balancesIncludeRow = array();
			$roamingUpdate = array();
			if (!is_null($this->balanceToUpdate)) {
				$packageLimits = $this->getPackageJoinedValues($this->balanceToUpdate['service_name'], $this->plan);
				if (!empty($packageLimits['joined_field'])) {
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
				Billrun_Factory::dispatcher()->trigger('addDataToUpdate', [$this->balanceToUpdate,&$row, &$pricingData, &$roamingQuery, &$roamingUpdate, $arate, $calculator]);
				$balanceIds[] = $this->balanceToUpdate->getRawData()['_id'];
				$this->balances->update($roamingQuery, $roamingUpdate, array('w' => 1));
				$balancesIncludeRow[] = array(
					'service_name' => $this->balanceToUpdate['service_name'],
					'package_id' =>  $this->balanceToUpdate['service_id'],
					'billrun_month' => $this->balanceToUpdate['billrun_month'],
					'added_usage' => floor($this->extraUsage / $this->coefficient),
					'added_joined_usage' => (!empty($joinedField) && in_array($row['usaget'], $joinedUsageTypes)) ? array('joined_field' => $joinedField, 'usage' => $this->extraUsage) : null,
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
						'added_usage' => $usageLeft,
						'added_joined_usage' => (!empty($packageLimits['joined_field']) && in_array($row['usaget'], $packageLimits['joined_usage_types'])) ? array('joined_field' => $packageLimits['joined_field'], 'usage' => $this->extraUsage) : null,
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
					Billrun_Factory::dispatcher()->trigger('addDataToUpdate', [$exhausted,&$row, &$pricingData, &$query, &$exhaustedUpdate, $arate, $calculator]);
					$balanceIds[] = $exhaustedBalance['_id'];
					$this->balances->update(array('_id' => $exhaustedBalance['_id']), $exhaustedUpdate);
				}
			}
			if (isset($exhaustedBalancesKeys)) {
				$balancesIncludeRow = array_merge($balancesIncludeRow, $exhaustedBalancesKeys);
			}
			if (!empty($balancesIncludeRow)) {
				$row['roaming_balances'] = $balancesIncludeRow;
				$this->updateRoamingBalancesTx($row, $balanceIds);
			}
		}
	}
	
	public function afterUpdateSubscriberBalance($row, $balance, &$pricingData, $calculator) {
		if (!is_null($this->package) && (($row['type'] == 'tap3' && in_array($row['usaget'], array('call', 'data', 'incoming_call'))) || isset($row['roaming']))) {	
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
		if ( !isset($this->lineType) || empty($limits['roaming']) ) {
			return;
		}
		$matchedPackages = array_filter($this->ownedPackages, function($package) use ($usageType, $rate) {
			return in_array($package['service_name'], $rate['rates'][$usageType]['groups']);
		});	
		if (empty($matchedPackages) || !$this->checkPackageCorrelation($groupSelected, $matchedPackages)) {
			$groupSelected = FALSE;
			return;
		}
		$this->plan = $plan;
		$UsageIncluded = 0;
		$subscriberSpent = 0;
		$matchedIds= [];
		foreach ($matchedPackages as $package) {

			$from = empty($package['balance_from_date']) ? strtotime($package['from_date']) : $package['balance_from_date'];
			$to = empty($package['balance_to_date']) ? strtotime($package['to_date']) : $package['balance_to_date'];

			$legitimate= (bool)($this->lineTime >= $from && $this->lineTime <= $to);
			Billrun_Factory::dispatcher()->trigger('checkPackageRules', [&$legitimate,$package,$this->row,$plan, $usageType, $rate, $subscriberBalance]);
			if(!$legitimate) {	continue;	}

			$matchedIds[] = $package['id'];

			$usageType = $this->getTransformedUsageType($package['service_name'], $plan, $usageType);
			$billrunKey = $package['service_name'] . '_' . date("Ymd", $from) . '_' . date("Ymd", $to) . '_' . $package['id'];
			$this->createRoamingPackageBalanceForSid($subscriberBalance, $billrunKey, $plan, $from, $to, $package['id'], $package['service_name']);
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
			'service_id' => array('$in' => $matchedIds),
		);
		$roamingBalances = $this->balances->query($roamingQuery)->cursor();
		if ($roamingBalances->current()->isEmpty()) {
			Billrun_Factory::log()->log("Didn't found roaming balance for sid:" . $subscriberBalance['sid'] . ' row stamp:' . $this->row['stamp'], Zend_Log::NOTICE);
			$groupSelected = FALSE;
			return;
		}
		foreach ($roamingBalances as $balance) {
			foreach ($matchedPackages as $matchedPackage) {
				if ($balance['service_id'] == $matchedPackage['id']) {
					$roamingBalancesByOrder[$matchedPackage['balance_priority']] = $balance;
				}
			}
		}

		ksort($roamingBalancesByOrder);
		foreach ($roamingBalancesByOrder as $balance) {
			$balancePackage = $balance['service_name'];
			$usageType = $this->getTransformedUsageType($balancePackage, $plan, $this->row['usaget']);
			if (!isset($plan->get('include.groups.' . $balancePackage)[$usageType])) {
				continue;
			}
			$subRaw = $balance->getRawData();
			$stamp = strval($this->row['stamp']);
			$txValue = isset($subRaw['tx']) && array_key_exists($stamp, $subRaw['tx']) ? $subRaw['tx'][$stamp]['usagev'] : 0;
			$planUsage = $plan->get('include.groups.' . $balancePackage)[$usageType];
			if ($planUsage == 'UNLIMITED') {
				$rateUsageIncluded = 'UNLIMITED';
				$groupSelected = $balancePackage;
				$this->package = $balancePackage;
				$this->balanceToUpdate = $balance;
				return;
			}
			$UsageIncluded += (int) $planUsage;
			if (isset($balance['balance']['totals'][$usageType])) {
				$subscriberSpent += $balance['balance']['totals'][$usageType]['usagev'] - $txValue;
				$usageLeft = (int) $planUsage - $balance['balance']['totals'][$usageType]['usagev'];
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
		
		if(floor($roundedUsage - $subscriberBalance['balance']['groups'][$groupSelected][$this->row['usaget']]['usagev']) <= 0) {
			$this->balanceToUpdate = null;
			$this->exhaustedBalances = array();
			$groupSelected = FALSE;
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
	protected function createRoamingPackageBalanceForSid($subscriberBalance, $billrunKey, $plan, $from, $to, $serviceId, $serviceName) {
		$planRef = $plan->createRef();
		$packageLimits = $this->getPackageJoinedValues($serviceName, $plan);
		if (!empty($packageLimits['joined_field'])) {
			Billrun_Balance::createBalanceIfMissing($subscriberBalance['aid'], $subscriberBalance['sid'], $billrunKey, $planRef, '', $from, $to, $serviceId, $serviceName, $packageLimits['joined_field']);
		} else {
			Billrun_Balance::createBalanceIfMissing($subscriberBalance['aid'], $subscriberBalance['sid'], $billrunKey, $planRef, '', $from, $to, $serviceId, $serviceName);
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
	 * method to update roaming balances once the regular balance was removed.
	 * 
	 */
	public function afterResetBalances($rebalanceSids, $rebalanceStamps, $billrunKey) {
		if (empty($this->rebalanceUsageSubtract)) {
			return;
		}
		$balancesColl = Billrun_Factory::db(array('name' => 'balances'))->balancesCollection()->setReadPreference('RP_PRIMARY');
		$sidsAsKeys = array_flip($rebalanceSids);
		$balancesToUpdate = array_intersect_key($this->rebalanceUsageSubtract, $sidsAsKeys);			
		$queryBalances = array(
			'sid' => array('$in' => array_keys($balancesToUpdate)),
		);
		$balances = $balancesColl->query($queryBalances)->cursor();
		foreach ($balancesToUpdate as $sid => $packageUsage) {
			foreach ($packageUsage as $packageId => $usageByUsaget) {
				$balanceToUpdate = $this->getRelevantBalance($balances, $packageId);
				$updateData = $this->buildUpdateBalance($balanceToUpdate, $usageByUsaget);
				
				$query = array(
					'sid' => $sid,
					'service_id' => $packageId,
				);
				
				$balancesColl->update($query, $updateData);
			}
		}
			
		$this->rebalanceUsageSubtract = array();
		$linesColl = Billrun_Factory::db()->linesCollection()->setReadPreference('RP_PRIMARY');
		$linesColl->update(array('stamp' => array('$in' => $rebalanceStamps)), array('$unset' => array('roaming_balances' => 1)), array('multiple' => true));
	}
	
	/**
	 * method to calculate the usage need to be subtracted from the roaming balance.
	 * 
	 * @param type $line
	 * 
	 */
	public function beforeResetLines($line) {
		if (!isset($line['roaming_balances'])) {
			return;
		}	
		foreach ($line['roaming_balances'] as $roamingBalance) {
			$packageId = $roamingBalance['package_id'];
			$aggregatedUsage = isset($this->rebalanceUsageSubtract[$line['sid']][$packageId][$line['usaget']]['usage'] ) ? $this->rebalanceUsageSubtract[$line['sid']][$packageId][$line['usaget']]['usage'] : 0;
			$this->rebalanceUsageSubtract[$line['sid']][$packageId][$line['usaget']]['usage'] = $aggregatedUsage + $roamingBalance['added_usage'];
			@$this->rebalanceUsageSubtract[$line['sid']][$packageId][$line['usaget']]['count'] += 1; 
			if (!is_null($roamingBalance['added_joined_usage'])) {
				$joinedField = $roamingBalance['added_joined_usage']['joined_field'];
				$joinedUsage = $roamingBalance['added_joined_usage']['usage'];
				$aggregatedJoinedUsage = isset($this->rebalanceUsageSubtract[$line['sid']][$packageId][$joinedField]['usage']) ? $this->rebalanceUsageSubtract[$line['sid']][$packageId][$joinedField]['usage'] : 0;
				$this->rebalanceUsageSubtract[$line['sid']][$packageId][$joinedField]['usage'] = $aggregatedJoinedUsage + $joinedUsage;
				@$this->rebalanceUsageSubtract[$line['sid']][$packageId][$joinedField]['count'] += 1;
			}
		}
	}
	
	protected function getRelevantBalance($balances, $packageId) {
		foreach ($balances as $balance) {
			$rawData = $balance->getRawData();
			if (isset($rawData['service_id'] ) && $rawData['service_id'] == $packageId) {
				return $rawData;
			}
		}
	}
	
	protected function buildUpdateBalance($balance, $volumeToSubstract) {
		$update = array();
		foreach ($volumeToSubstract as $usaget => $usagev) {
			if (isset($balance['balance']['totals'][$usaget]['usagev'])) {
				$update['$set']['balance.totals.' . $usaget . '.usagev'] = $balance['balance']['totals'][$usaget]['usagev'] - $usagev['usage'];
				$update['$set']['balance.totals.' . $usaget . '.count'] = $balance['balance']['totals'][$usaget]['count'] - $usagev['count'];
			}
			if (isset($balance['balance']['totals'][$usaget]['exhausted']) && ($usagev['usage'] > 0)) {
				$update['$unset']['balance.totals.' . $usaget . '.exhausted'] = 1;
			}
		}
		return $update;
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

	public function handleExtraBalancesOnCrash(&$pricingData, $row) {
		$stamp = strval($row['stamp']);
		$balanceQuery = array(
			'sid' => $row['sid'],
			'$and' => array(
				array('to' => array('$exists' => true)),
				array('to' => array('$gte' => new MongoDate($row['urt']->sec))),
				array('from' => array('$exists' => true)),
				array('from' => array('$lte' => new MongoDate($row['urt']->sec)))
			),
		);
		$roamingBalances = $this->balances->query($balanceQuery)->cursor();
		foreach ($roamingBalances as $balance) {
			if (isset($balance['tx'][$stamp])) {
				$pricingData['roaming_balances'] = $balance['tx'][$stamp]['roaming_balances'];
				$ids[] = $balance->getRawData()['_id'];
			}
		}

		if (!empty($ids)) {
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
	}

	protected function updateRoamingBalancesTx($row, $balanceIds) {
		$this->balances->update(array('_id' => array('$in' => $balanceIds)), array('$set' => array('tx.' . $row['stamp'] . '.roaming_balances' => $row['roaming_balances'])));
	}
	
	protected function checkPackageCorrelation($groupSelected, $matchedPackages) {
		foreach ($matchedPackages as $matchedPackage) {
			$includedServices[] = $matchedPackage['service_name'];
		}
		return in_array($groupSelected, $includedServices);
	}

}
