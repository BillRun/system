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
 * @subpackage  row
 * @since       5.3
 * @todo probably this is not specific for prepaid but for real-time
 */
class Billrun_Calculator_Row_Customerpricing_Prepaid extends Billrun_Calculator_Row_Customerpricing {

	protected function init() {
		parent::init();
		$this->initMinBalanceValues();
		$this->loadSubscriberBalance();
		$this->row['granted_return_code'] = Billrun_Factory::config()->getConfigValue('realtime.granted_code.ok');
		$this->initMinBalanceValues();
		if (!(isset($this->row['prepaid_rebalance']) && $this->row['prepaid_rebalance']) && $this->balance) { // If it's a prepaid row, but not rebalance
			$this->row['usagev'] = Billrun_Rates_Util::getPrepaidGrantedVolume($this->row, $this->rate, $this->balance, $this->usaget, $this->balance->getBalanceChargingTotalsKey($this->usaget), $this->getCallOffset(), $this->min_balance_cost, $this->min_balance_volume, $this->row['urt']->sec);
			$this->row['apr'] = Billrun_Rates_Util::getTotalChargeByRate($this->rate, $this->row['usaget'], $this->row['usagev'], $this->row['plan'], $this->getServices(), $this->getCallOffset(), $this->row['urt']->sec);
		} else {
			$this->row['apr'] = 0;
			$this->row['usagev'] = 0;
		}
	}
	
	/**
	 * see parent::initMinBalanceValues
	 * just adds 2 additional internal variables that were mistakenly used in the code without touching postpaid logic
	 */
	protected function initMinBalanceValues() {
		parent::initMinBalanceValues();
		$this->granted_volume = $this->min_balance_volume;
		$this->granted_cost = $this->min_balance_cost;
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
				$granted_volume = Billrun_Rates_Util::getPrepaidGrantedVolumeByRate($this->rate, $this->row['usaget'], $this->plan->getName(), $this->getCallOffset(), $this->min_balance_cost, $this->min_balance_volume, $this->row['urt']->sec, $this->row['usagev']);
			}
			$charges = Billrun_Rates_Util::getChargesByRate($this->rate, $this->row['usaget'], $granted_volume, $this->plan->getName(), $this->getServices(), $this->getCallOffset(), $this->row['urt']->sec);
			$granted_cost = $charges['total'];
			return array(
				$this->pricingField => $granted_cost,
				'usagev' => $granted_volume,
			);
		}
		$this->row['granted_return_code'] = Billrun_Factory::config()->getConfigValue('realtime.granted_code.no_available_balances');
		$this->row['usagev'] = 0;
		$this->row['apr'] = 0;
		return parent::handleNoBalance();
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
	}

	public function update() {
		$ret = parent::update();
		if ($ret === false) { // on prepaid there is no retry for the event, so the upper layer will declare this event as not priced
			return true;
		}
		return $ret;
	}
	
	/**
	 * In case balance is in over charge (due to prepaid mechanism), 
	 * adds a refund row to the balance.
	 * 
	 * @param flaot $rebalanceUsagev amount of balance (usagev) to return to the balance
	 * @param float $realUsagev
	 * @param array $lineToRebalance
	 * @param array $originalRow
	 */	
	protected function handleRebalanceRequired($rebalanceUsagev, $realUsagev, $lineToRebalance, $originalRow) {
		// Update subscribers balance
		$balanceRef = $lineToRebalance->get('balance_ref', true);
		if (!$balanceRef) {
			$balance = null;
		} else {
			// Update balance usagev
			$balances_coll = Billrun_Factory::db()->balancesCollection();
			$balance = $balances_coll->getRef($balanceRef);
			if (is_array($balance['tx']) && empty($balance['tx'])) { //TODO: this is a hack because tx is saved as [] instead of {}
				$balance['tx'] = new stdClass();
			}
			$balance->collection($balances_coll);
			$balance_totals_key =  $this->balance->getBalanceTotalsKey($lineToRebalance);
			
			$rebalanceData = array(
				'usagev' => $rebalanceUsagev,
				'in_balance_usage' => $rebalanceUsagev,
			);
			
			if (!is_null($balance['balance.totals.' . $balance_totals_key . '.usagev'])) {
				$balance['balance.totals.' . $balance_totals_key . '.usagev'] += $rebalanceUsagev;
			} else {
				$rebalanceCost = $this->getRebalanceCost($lineToRebalance, $realUsagev, $rebalanceUsagev);
				if (!is_null($balance['balance.totals.' . $balance_totals_key . '.cost'])) {
					$balance['balance.totals.' . $balance_totals_key . '.cost'] += $rebalanceCost;
				} else {
					$balance['balance.cost'] += $rebalanceCost;
				}
				
				$rebalanceData['apr'] = $rebalanceData['aprice'] = $rebalanceCost;
			}
			
			$balance->save();
		}
		
		$originalRow['usagev_offset'] += $rebalanceUsagev;
		
		$updateLinesQuery = $this->getUpdateLineUpdateQuery($rebalanceData);
		$updateArchiveQuery = $this->getUpdateLineUpdateQuery(array_merge($rebalanceData, $this->getAdditionalUsagevFieldsForArchive($rebalanceUsagev, $lineToRebalance)));
		
		// Update line in archive
		$lines_archive_coll = Billrun_Factory::db()->archiveCollection();
		$lines_archive_coll->update(array('_id' => $lineToRebalance->getId()->getMongoId()), $updateArchiveQuery);

		// Update line in Lines collection will be done by Unify calculator
		$sessionQuery = $this->getSessionIdQuery($lineToRebalance->getRawData());
		$findQuery = array_merge(array("sid" => $lineToRebalance['sid']), $sessionQuery);
		$lines_coll = Billrun_Factory::db()->linesCollection();
		$options = array('multiple' => true); // this option is added in case we have sharding key=stamp and the update cannot be done
		$lines_coll->update($findQuery, $updateLinesQuery, $options);
	}
		
	/**
	 * gets the price of the rebalance
	 * 
	 * @param array $lineToRebalance
	 * @param float $realUsagev
	 * @return float
	 */
	protected function getRebalanceCost($lineToRebalance, $realUsagev, $rebalanceUsagev) {
		$lineToRebalanceRate = $this->getRowRate($lineToRebalance);
		$realPricing = Billrun_Rates_Util::getTotalCharge($lineToRebalanceRate, $lineToRebalance['usaget'], $realUsagev, $lineToRebalance['plan'], $this->getServices(), 0, $lineToRebalance['urt']->sec);
		$chargedPricing = Billrun_Rates_Util::getTotalCharge($lineToRebalanceRate, $lineToRebalance['usaget'], $realUsagev - $rebalanceUsagev, $lineToRebalance['plan'], $this->getServices(), 0, $lineToRebalance['urt']->sec);
		return $realPricing - $chargedPricing;
	}
	
	/**
	 * gets all fields that needs to be rebalanced by volume in the archive collection
	 * 
	 * @param float $usagev
	 * @param array $lineToRebalance
	 * @return array
	 */
	protected function getAdditionalUsagevFieldsForArchive($usagev, $lineToRebalance) {
		$ret = array();
		$availableFields = array('in_group', 'out_group', 'over_group', 'in_plan', 'out_plan', 'over_plan');
		foreach ($availableFields as $field) {
			if (isset($lineToRebalance[$field])) {
				$ret[$field] = $usagev;
			}
		}
		
		return $ret;
	}
	
}
