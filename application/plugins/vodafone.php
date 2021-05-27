<?php

/**
 * @package         Billing
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
	protected $vfConfig = null;
	protected $count_days;
	protected $premium_ir_not_included = null;
	
	public function __construct() {
		$this->transferDaySmsc = Billrun_Factory::config()->getConfigValue('billrun.tap3_to_smsc_transfer_day', "20170301000000");
		$this->balances = Billrun_Factory::db()->balancesCollection()->setReadPreference('RP_PRIMARY');
		$this->vfConfig = Billrun_Factory::config()->getConfigValue('vodafone', []);
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
	public function planGroupRule(&$groupSelected, $limits, $plan, $usageType, $rate, $subscriberBalance) {
		if (!in_array($groupSelected, Billrun_Util::getIn($this->vfConfig, 'groups', [])) || !isset($this->line_type)) {
			return;
		}
		if (!empty($this->premium_ir_not_included)) {
			$groupSelected = FALSE;
			return;
		}
		if ($this->line_type == 'tap3' && $usageType == 'sms') {
			return;
		}
		$updated_dates_count = array_unique(array_merge(!empty($subscriberBalance->getRawData()['balance']['dates']) ? $subscriberBalance->getRawData()['balance']['dates'] : [] , [date("Y-m-d", strtotime($this->line_time))]));
		if (count($updated_dates_count) <= intval($limits['days'])) {
			return;
		}
		
		$rateUsageIncluded = 0; // user passed its limit; no more usage available
		$groupSelected = FALSE; // we will cancel the usage as group plan when set to false groupSelected
	}
	
	public function beforeCommitSubscriberBalance(&$row, &$pricingData, &$query, &$update, $rate, $calculator, $balanceToUpdate) {
		if (!empty($balanceToUpdate['vf'])) {
			$path = 'balance.dates';
			$update['$addToSet'][$path] = date('Y-m-d', $row['urt']->sec);
		}
	}

	/**
	 * removes the transactions from the subscriber's add-on balance to save space.
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
	
	public function beforeCreateBasicBalance(&$update, $aid, $sid, $from, $to, $plan, $urt, $start_period, $period, $service_name, $priority) {
		if (!empty($service_name)) {
			$serviceSettings = array(
				'name' => $service_name,
				'time' => $urt,
				'disableCache' => false
			);
			$serviceObject = Billrun_Factory::service($serviceSettings);
			if (!empty($serviceObject->getData(true)['vf'])) {
				$update['$setOnInsert']['vf'] = true;
			}
		}
	}

}
