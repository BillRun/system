<?php
/**
 * @package	Billing
 * @copyright	Copyright (C) 2012-2018 BillRun Technologies Ltd. All rights reserved.
 * @license	GNU Affero General Public License Version 3; see LICENSE.txt
 */
/**
 * 
 * 
 * @package  Application
 * @subpackage Plugins
 * @since    5.8
 */
class freeAboveThresholdPlugin extends Billrun_Plugin_BillrunPluginBase
{

	/**
	 * Pinding updates to be applied on balances
	 *
	 * @var array
	 */
	protected $pendingBalanceUpdates;
	public function __construct() {
		$this->concurrentMaxRetries = (int) Billrun_Factory::config()->getConfigValue('updateValueEqualOldValueMaxRetries', 8);
		$this->pendingBalanceUpdates = [];
	}

	public function beforeCommitSubscriberBalance(&$row, &$pricingData, &$query, &$update, $arate, $calculator, $balanceToUpdate){
		$updateKey = $this->getBalanceUpdateKey($balanceToUpdate);
		if(!empty($this->pendingBalanceUpdates[$updateKey])) {
			$updateField = $this->pendingBalanceUpdates[$updateKey]['volumeIncKey'];
			//$previousValue = empty($balanceToUpdate[$updateKey]) ? 0 : $balanceToUpdate[$updateKey];
			$update['$inc'][$updateField] = $row['usagev'];

		}
		//Billrun_Factory::log(print_r($balanceToUpdate));
		Billrun_Factory::log($updateKey);
// 		Billrun_Factory::log(print_r($update));
// 		Billrun_Factory::log(print_r($this->pendingBalanceUpdates));
	}

	public function afterUpdateSubscriberBalance($row, $balance, &$pricingData, $calculator) {
		$updateKey = $this->getBalanceUpdateKey($balance);
		if(!empty($this->pendingBalanceUpdates[$updateKey])) {
			unset($this->pendingBalanceUpdates[$updateKey]);
		}
	}

	/**
	 * method to override the plan group limits
	 *
	 */
	public function planGroupRule(&$rateUsageIncluded, &$groupSelected, $limits, $plan, $usageType, $rate, $subscriberBalance) {
		if(!$this->isValidUsage($limits,$rate,$plan,$usageType)) {
			Billrun_Factory::log("not VALID : ".json_encode($limits));
			$groupSelected = FALSE;
			return ;
		}
		// Below the threashold no usage included in the group
		if(	empty($subscriberBalance['balance']['groups'][$groupSelected]['rates'][$rate['key']][$usageType]) ||
			$subscriberBalance['balance']['groups'][$groupSelected]['rates'][$rate['key']][$usageType] < $rate['rates'][$usageType]['free_threshold']) {
			$rateUsageIncluded = 0;
			@Billrun_Factory::log("Below Threshold : ".  $rate['rates'][$usageType]['free_threshold']);
			@Billrun_Factory::log("Below Threshold : ". $subscriberBalance['balance']['groups'][$groupSelected]['rates'][$rate['key']][$usageType]);
			@Billrun_Factory::log(print_r($subscriberBalance['balance']['groups']));
		} else {
			// Above the threshold  so te usage if free / included
			$rateUsageIncluded = PHP_INT_MAX;
		}
		Billrun_Factory::log("VALID : ".json_encode($limits) . "  returning : $rateUsageIncluded ");
		// save the rate tand the field to be updated in the balance
		$update=['volumeIncKey'=>"balance.groups.{$groupSelected}.rates.{$rate['key']}.{$usageType}" ];
		$updateKey=$this->getBalanceUpdateKey($subscriberBalance);
		$this->pendingBalanceUpdates[$updateKey] = $update;
		Billrun_Factory::log($updateKey);
	}

	protected function isValidUsage($limits,$rate,$plan,$usageType) {
		return (
				!empty($limits['free_above_threshold'])  &&
				!empty($usageType) &&
				!empty($rate['rates'][$usageType]) && !empty($rate['rates'][$usageType]['free_threshold'])
			);
	}

	protected function getBalanceUpdateKey($balanceToUpdate) {
		return Billrun_Util::generateArrayStamp([$balanceToUpdate['billrun_month'],$balanceToUpdate['aid'],$balanceToUpdate['sid'],$balanceToUpdate['_id']]);
	}
}
