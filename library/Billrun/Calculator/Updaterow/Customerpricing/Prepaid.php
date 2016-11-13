<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator update row for customer pricing prepaid calc in row level
 *
 * @package     calculator
 * @subpackage  updaterow
 * @since       5.3
 */
class Billrun_Calculator_Updaterow_Customerpricing_Prepaid extends Billrun_Calculator_Updaterow_Customerpricing {

	protected function init() {
		parent::init();
		$this->row['granted_return_code'] = Billrun_Factory::config()->getConfigValue('prepaid.ok');
		$this->initMinBalanceValues($this->rate, $this->row['usaget'], $this->plan);
		if (!(isset($this->row['prepaid_rebalance']) && $this->row['prepaid_rebalance'])) { // If it's a prepaid row, but not rebalance
			$this->row['apr'] = Billrun_Rates_Util::getTotalChargeByRate($this->rate, $this->row['usaget'], $this->row['usagev'], $this->row['plan'], $this->getCallOffset());
			$this->row['balance_ref'] = $this->balance->createRef();
			$this->row['usagev'] = Billrun_Rates_Util::getPrepaidGrantedVolume($this->row, $this->rate, $this->balance, $this->usaget, $this->balance->getBalanceChargingTotalsKey(sagesage_type), $this->getCallOffset(), $this->min_balance_cost, $this->min_balance_volume);
		} else {
			$this->row['usagev'] = 0;
		}
	}

	/**
	 * method that handle cases when balance is not available (on real-time)
	 * @return boolean true if you want to continue even if there is no available balance else false
	 */
	protected function handleNoBalance() {
		if ($this->isFreeLine()) {
			return $this->balance->getFreeRowPricingData();
		}
		// check first if this free call and allow it if so
		if ($this->min_balance_cost == '0') { // @TODO: check why we put string and not int
			if (isset($this->row['api_name']) && in_array($this->row['api_name'], array('start_call', 'release_call'))) {
				$granted_volume = 0;
			} else {
				$granted_volume = Billrun_Rates_Util::getPrepaidGrantedVolumeByRate($this->rate, $this->row['usaget'], $this->plan->getName(), $this->getCallOffset(), $this->min_balance_cost, $this->min_balance_volume);
			}
			$charges = Billrun_Rates_Util::getChargesByRate($this->rate, $this->row['usaget'], $granted_volume, $this->plan->getName(), $this->getCallOffset());
			$granted_cost = $charges['total'];
			return array(
				$this->pricingField => $granted_cost,
				'usagev' => $granted_volume,
			);
		}
		$this->row['granted_return_code'] = Billrun_Factory::config()->getConfigValue('prepaid.customer.no_available_balances');
		parent::handleNoBalance();
	}

	protected function isBillable() {
		return false;
	}

	/**
	 * plugin-able check if line is free
	 * 
	 * @param array $row row to check
	 * 
	 * @return true if it's free row else false
	 */
	protected function isFreeLine() {
		$isFreeLine = false;
		Billrun_Factory::dispatcher()->trigger('isFreeLine', array(&$this->row, &$isFreeLine));
		return $this->row['free_line'] = boolval($isFreeLine);
		;
	}

	public function update() {
		$ret = parent::update();
		if ($ret === false) { // on prepaid there is no retry for the event, so the upper layer will declare this event as not priced
			return true;
		}
		return $ret;
	}

}
