<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Vodafone plugin for vodafone special rates
 *
 * @package  Application
 * @subpackage Plugins
 * @since    2.8
 */
class vodafonePlugin extends Billrun_Plugin_BillrunPluginBase {

	/**
	 * @var Mongodloid_Collection 
	 */
	protected $balances = null;
	protected $count_days;
	protected $premium_ir_not_included = null;
	
	public function __construct() {
		$this->transferDaySmsc = Billrun_Factory::config()->getConfigValue('billrun.tap3_to_smsc_transfer_day', "20170301000000");
		$this->balances = Billrun_Factory::db()->balancesCollection()->setReadPreference('RP_PRIMARY');
	}

	public function beforeUpdateSubscriberBalance($balance, $row, $rate, $calculator) {
		if ($row['type'] == 'tap3' || isset($row['roaming'])) {
			if (isset($row['urt'])) {
				$timestamp = $row['urt']->sec;
				$this->line_type = $row['type'];
				$this->line_time = date("YmdHis", $timestamp);
			} else {
				Billrun_Factory::log()->log('urt wasn\'t found for line ' . $row['stamp'] . '.', Zend_Log::ALERT);
			}
		}
		if (!empty($row['premium_ir_not_included'])) {
			$this->premium_ir_not_included = $row['premium_ir_not_included'];
		} else {
			$this->premium_ir_not_included = null;
		}
	}

	public function afterUpdateSubscriberBalance($row, $balance, &$pricingData, $calculator) {
		if (!is_null($this->count_days) && empty($this->premium_ir_not_included) && !empty($pricingData['arategroup']) && in_array($pricingData['arategroup'], ['VF', 'IRP_VF_10_DAYS'])) {
			$pricingData['vf_count_days'] = $this->count_days;
		}
		$this->removeRoamingBalanceTx($row, $balance->getId()->getMongoID());
		$this->count_days = NULL;
		$this->limit_count = [];
		$this->usage_count = [];
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
		if ($groupSelected != 'VF' || !isset($this->line_type)) {
			return;
		}
		if (!empty($this->premium_ir_not_included)) {
			$groupSelected = FALSE;
			return;
		}
		if ($this->line_type == 'tap3' && $usageType == 'sms' && $this->line_time >= $this->transferDaySmsc) {
			return;
		}

		$this->count_days = $this->getSidDaysCount($subscriberBalance['sid'], $limits, $plan, $groupSelected);
		$this->limit_count['VF'] = $limits['days'];
		if ($this->count_days <= $limits['days']) {
			return;
		}
		
		$rateUsageIncluded = 0; // user passed its limit; no more usage available
		$groupSelected = FALSE; // we will cancel the usage as group plan when set to false groupSelected
	}
	
	public function beforeCommitSubscriberBalance($row, $pricingData, $query, $update, $rate, $calculator) {
		foreach($pricingData['arategroups'] as $index => $group) {
			$path = 'balance.groups.' . $group['name'] . '.dates';
			$update['$addToSet'][$path] = date('Y-m-d', $row['urt']->sec);
			$this->balances->update($query, $update, array('w' => 1));
		}
	}

	/**
	 * removes the transactions from the subscriber's addon balance to save space.
	 * @param type $row
	 */
	protected function removeRoamingBalanceTx($row, $balance_id){
		$query = array(
			'_id' => array('$in' => [$balance_id]),
		);
		$update = array(
			'$unset' => array(
				'tx.' . $row['stamp'] => 1
			)
		);
		$this->balances->update($query, $update);
	}
}
