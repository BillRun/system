
<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2025 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */


/**
 * Roaming Packages plugin for roaming packages.
 *
 * @package  Application
 * @subpackage Plugins
 * @since    2.8
 */
class nonBillablePackagesPlugin extends Billrun_Plugin_BillrunPluginBase {


	protected $package = null;

	/**
	 * @var Mongodloid_Collection
	 */
	protected $balances = null;


	protected $ownedPackages = null;


	/**
	 * The balance to update.
	 *
	 * @var Mongodloid_Entity
	 */
	protected $balanceToUpdate = [];

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




	public function __construct() {
		$this->balances = Billrun_Factory::db(array('name' => 'balances'))->balancesCollection()->setReadPreference('RP_PRIMARY');
		$this->concurrentMaxRetries = (int) Billrun_Factory::config()->getConfigValue('updateValueEqualOldValueMaxRetries', 8);
	}

	public function beforeUpdateSubscriberBalance($balance, $row, $rate, $calculator) {
		$this->row = null;

		if ( $this->isRowRoaming($row) ) {
			$this->row = $row;
		}

		$this->ownedPackages = !empty($row['packages']) ? $row['packages'] : [];
		$this->balancesToUpdate = [];
		$this->package = null;
		$this->plan = null;
	}

	public function beforeCommitSubscriberBalance(&$row, &$pricingDataOrg, &$query, &$update, $arate, $calculator) {
		if ( !is_null($this->package) && $this->isRowRoaming($row) && !empty($this->balancesToUpdate)) {
			$balancesIncludeRow = array();
			$nonBillableUpdate = array();
			$updatedBalancesDataToRow = [];
			$pricingData = $pricingDataOrg;

			$nonBillableQueryBase = array(
				'sid' => $row['sid'],
				'tx.' . $row['stamp'] => array('$exists' => false)
			);
			$previousCostLeft = 0;
			foreach($this->balancesToUpdate as  $balanceToUpdateData) {
				$balanceToUpdate = $balanceToUpdateData['balance'];
				$isExhusted=false;
				Billrun_Factory::log()->log("Updating balance " . $balanceToUpdate['billrun_month'] . " of subscriber " . $row['sid'], Zend_Log::DEBUG);
				$nonBillableQuery = array_merge($nonBillableQueryBase, [ 'billrun_month' => $balanceToUpdate['billrun_month'], 'service_name'=>$balanceToUpdate['service_name'] ]);
				$dataToUpdate = @$pricingData['groups'][$balanceToUpdate['service_name']];
				if(empty($dataToUpdate)) {
					$dataToUpdate = [ 'usagev' => 0 , 'price' =>  0 ];
				}
				$dataToUpdate['price'] += $previousCostLeft;
				$costLeft = $balanceToUpdateData['package_data']['cost'] - ($dataToUpdate['price'] + $balanceToUpdate['balance']['cost']);
				if($costLeft < 0 ) {
					$dataToUpdate['price'] += $costLeft;
					$previousCostLeft = -$costLeft;
					$isExhusted = true;

				}
				if(!empty($dataToUpdate['usagev']) || !empty($dataToUpdate['price'])) {
					$updatedBalancesDataToRow[] = [
						'billrun_month' => $balanceToUpdate['billrun_month'] ,
						'package_id' => $balanceToUpdate['service_id'],
						'service_name' => $balanceToUpdate['service_name'],
						'usage_before' => [
							'call' => $balanceToUpdate['balance']['totals']['call']['usagev'],
							'incoming_call' => $balanceToUpdate['balance']['totals']['incoming_call']['usagev'],
							'sms' => $balanceToUpdate['balance']['totals']['sms']['usagev'],
							'data' => $balanceToUpdate['balance']['totals']['data']['usagev'],
							'cost' => @$balanceToUpdate['balance']['totals']['cost']
						],
						'usage_added' => $dataToUpdate
					];

					$this->updateNonBillableBalance($nonBillableQuery, $balanceToUpdate['service_name'],
													$dataToUpdate, $row, $isExhusted,
													$balanceToUpdate, $arate, $calculator );
					$balanceIds[] = $balanceToUpdate->getRawData()['_id'];
					unset($pricingData['groups'][$balanceToUpdate['service_name']]);
				} else {
					// Last balance that was affected
					break;
				}
			}

			if(!empty($updatedBalancesDataToRow)) {
				$row['balances_affected'] = array_merge( (empty($row['balances_affected']) ? [] : $row['balances_affected'] ),
														 $updatedBalancesDataToRow );
			}

			if ( !empty($balanceIds) ) {
				$this->updateRoamingBalancesTx($row, $balanceIds);
			}
		}
	}

	public function afterUpdateSubscriberBalance($row, $balance, &$pricingData, $calculator) {
		if (!is_null($this->package) && $this->isRowRoaming($row) ) {
			$this->removeRoamingBalanceTx($row);
		}
	}

	protected  function updateNonBillableBalance($nonBillableQuery, $serviceName, $pricingData, $row ,$isExhusted = false , $balanceToUpdate= null , $arate = null , $calculator =null) {
		$nonBillableUpdate=[];
		$nonBillableUpdate['$inc']['balance.totals.' . $row['usaget'] . '.usagev'] = $pricingData['usagev'];
		$nonBillableUpdate['$inc']['balance.totals.' . $row['usaget'] . '.cost'] = $pricingData['price'];
		$nonBillableUpdate['$inc']['balance.totals.' . $row['usaget'] . '.count'] = 1;
		if( $isExhusted ) {
			$nonBillableUpdate['$set']['balance.totals.exhausted'] = true;
			$nonBillableUpdate['$set']['balance.totals.' . $row['usaget'] . '.exhausted'] = true;
		}
		$nonBillableUpdate['$inc']['balance.totals.cost'] = $pricingData['price'];
		$nonBillableUpdate['$inc']['balance.cost'] = $pricingData['price'];
		$nonBillableUpdate['$set']['tx'][$row['stamp']] = array('package' => $serviceName,
																'usaget' => $row['usaget'],
																'usagev' => $pricingData['usagev'],
																'price'=>$pricingData['price']);

		//Billrun_Factory::dispatcher()->trigger('addDataToUpdate', [$balanceToUpdate,&$row, &$pricingData, &$nonBillableQuery, &$nonBillableUpdate, $arate, $calculator]);
		return $this->balances->update($nonBillableQuery, $nonBillableUpdate, array('w' => 1));
	}

	protected function isRowRoaming($row) {
		return (
				(($row['type'] == 'nrtrde' && in_array($row['usaget'], array('call', 'incoming_call'))) || 	$row['type'] == 'ggsn')
					||
				isset($row['roaming'])
			);
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
		if ( !isset($this->row) || empty($limits['no_billable_affects']) ) {
			return;
		}

		$matchedPackages = array_filter($this->ownedPackages, function($package) use ($usageType, $rate, $plan) {
			$isNonBillablle = @$plan->get('include.groups.'.$package['service_name'].'.limits.no_billable_affects');
			return 	in_array($package['service_name'], $rate['rates'][$usageType]['groups']) &&
					!empty($isNonBillablle);
		});
		if (empty($matchedPackages) || !in_array($groupSelected, array_column($matchedPackages,'service_name'))) {
			$groupSelected = FALSE;
			return;
		}

		if(!empty($this->balancesToUpdate)) {
			//Non billable balances were allready loaded  for this row
			$groupSelected = FALSE;
			return;
		}


		$UsageIncluded = 0;
		$subscriberSpent = 0;
		$matchedIds = [];

		foreach ($matchedPackages as $package) {

			$from = empty($package['balance_from_date']) ? strtotime($package['from_date']) : $package['balance_from_date'];
			$to = empty($package['balance_to_date']) ? strtotime($package['to_date']) : $package['balance_to_date'];
			$isNonBillablle = $plan->get('include.groups.'.$package['service_name'].'.limits.no_billable_affects');
			$legitimate = (bool)($this->row['urt']->sec >= $from && $this->row['urt']->sec <= $to || true) && !empty($isNonBillablle);
			//Billrun_Factory::dispatcher()->trigger('checkPackageRules', [&$legitimate,$package,$this->row,$plan, $usageType, $rate, $subscriberBalance]);
			if(!$legitimate) {
				if($groupSelected === $package['service_name']) {
					$groupSelected = FALSE;
					return;
				}
				continue;
			}

			$matchedIds[] = $package['id'];

			$billrunKey = $package['service_name'] . '_' . date("Ymd", $from) . '_' . date("Ymd", $to) . '_' . $package['id'];
			$this->createRoamingPackageBalanceForSid($subscriberBalance, $billrunKey, $plan, $from, $to, $package['id'], $package['service_name']);
		}

		//non-billable services does not have includes
		$rateUsageIncluded = 0;

		$nonBillableQuery = array(
			'sid' => $subscriberBalance['sid'],
			'$and' => array(
				array('to' => array('$exists' => true)),
				array('to' => array('$gte' => new MongoDate($this->row['urt']->sec))),
				array('from' => array('$exists' => true)),
				array('from' => array('$lte' => new MongoDate($this->row['urt']->sec)))
			),
			'service_id' => array('$in' => $matchedIds),
		);

		$nonBillableQuery['$or'][] = ['balance.totals.' . $usageType => ['$exists' => true], 'balance.totals.' . $usageType . '.exhausted' => ['$exists' => false]];
		$nonBillableQuery['$or'][] = ['balance.totals.' . $usageType => ['$exists' => true], 'balance.totals.' . $usageType . '.exhausted' => ['$ne' => true]];
		$nonBillableQuery['$or'][] = ['balance.totals.' . $usageType => ['$exists' => true], 'balance.totals.exhausted' => ['$ne' => true]];


		$nonBillableBalances = $this->balances->query($nonBillableQuery)->cursor();
		if ($nonBillableBalances->current()->isEmpty()) {
			if(!empty($matchedIds)) {
			Billrun_Factory::log()->log("Didn't found CAP balance for sid:" . $subscriberBalance['sid'] . ' row stamp:' . $this->row['stamp'], Zend_Log::NOTICE);
			}
			$groupSelected = FALSE;
			return;
		}
		$subIdx = 0;
		foreach ($nonBillableBalances as $balance) {
			foreach ($matchedPackages as $matchedPackage) {
				if ($balance['service_id'] == $matchedPackage['id']) {
					$nonBillableBalancesByOrder[$matchedPackage['balance_priority'].'.'.$subIdx++] = $balance;
				}
			}
		}
		ksort($nonBillableBalancesByOrder);
		foreach ($nonBillableBalancesByOrder as $balance) {
			$balancePackage = $balance['service_name'];

			if (!isset($plan->get('include.groups.' . $balancePackage)[$usageType]) &&
				empty($plan['include']['groups'][$balancePackage]['limits']['no_billable_affects'])) {
					continue;
			}
			$subRaw = $balance->getRawData();
			$stamp = strval($this->row['stamp']);
			$txValue = isset($subRaw['tx']) && array_key_exists($stamp, $subRaw['tx']) ? $subRaw['tx'][$stamp]['usagev'] : 0;
			$planUsage = $plan->get('include.groups.' . $balancePackage)[$usageType];

			$UsageIncluded += (int) $planUsage;
			if (isset($balance['balance']['totals'][$usageType])) {
				$this->package = $balancePackage;
				$this->balancesToUpdate[] = [ 'balance' => $balance, 'package_data' => $plan->get('include.groups.'.$balancePackage) ] ;
			}
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
		$packageLimits = $plan->get('include.groups.'.$serviceName.'.limits');
		Billrun_Balance::createBalanceIfMissing($subscriberBalance['aid'], $subscriberBalance['sid'], $billrunKey, $planRef, $from, $to, $serviceId, $serviceName);
	}

	/**
	 * removes the transactions from the subscriber's roaming balance to save space.
	 * @param type $row
	 */
	protected function removeRoamingBalanceTx($row){
		$ids = [];

		if (!empty($this->balancesToUpdate)) {
			foreach($this->balancesToUpdate as  $balanceToUpdateData) {
				array_push($ids, $balanceToUpdateData['balance']->getRawData()['_id']);
			}
		}
		if($ids) {
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


	public function handleRoamingBalancesOnCrash(&$pricingData, $row) {
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
		$nonBillableBalances = $this->balances->query($balanceQuery)->cursor();
		foreach ($nonBillableBalances as $balance) {
			if (isset($balance['tx'][$stamp])) {
				$pricingData['groups'] = $balance['tx'][$stamp]['groups'];
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
		$this->balances->update(['_id' => ['$in' => $balanceIds]], [ '$set' => [ 'tx.' . $row['stamp'] . '.groups' => $row['groups']]]);
	}

}
