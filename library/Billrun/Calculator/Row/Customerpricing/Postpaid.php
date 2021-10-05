<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator update row for customer pricing postpaid calc in row level
 *
 * @package     calculator
 * @subpackage  row
 * @since       5.3
 */
class Billrun_Calculator_Row_Customerpricing_Postpaid extends Billrun_Calculator_Row_Customerpricing {

	protected function init() {
		parent::init();
		$this->activeBillrunEndTime = $this->calculator->getActiveBillrunEndTime(); // todo remove this coupling
		$this->activeBillrun = $this->calculator->getActiveBillrun(); // todo remove this coupling
		$this->nextActiveBillrun = $this->calculator->getNextActiveBillrun(); // todo remove this coupling
		$this->nextActiveBillrunEndTime = Billrun_Billingcycle::getEndTime($this->nextActiveBillrun);
	}

	protected function validate() {
		if (!isset($this->row['usagev'])) {
			Billrun_Factory::log("Line with stamp " . $this->row['stamp'] . " is missing volume information", Zend_Log::ALERT);
			return false;
		}
		return parent::validate();
	}

	public function update($pricingOnly = false) {
		$pricingData = parent::update($pricingOnly);
		$customerInvoicingDay = isset($this->row['foreign']['account']) ? isset($this->row['foreign']['account']['invoicing_day'])? $this->row['foreign']['account']['invoicing_day'] : null : null;
		$nonMonthlyPlanConfig = $this->plan && $this->plan->isNonMonthly() ? $this->plan->getRecurrenceConfig() : null;
		$config = Billrun_Factory::config();
		if(!empty($nonMonthlyPlanConfig)) {
			$noneMonthlyConfig['recurrence'] = $nonMonthlyPlanConfig;
			$noneMonthlyConfig['activation_date'] = Billrun_Util::getFieldVal($this->row['foreign']['subscriber']['activation_date'],null);
			$activeBillrun = Billrun_Billrun::getActiveBillrun($customerInvoicingDay,$noneMonthlyConfig);
			$activeBillrunEndTime = Billrun_Billingcycle::getEndTime($activeBillrun, $customerInvoicingDay,$noneMonthlyConfig);
			$nextActiveBillrun = Billrun_Billingcycle::getFollowingBillrunKey($activeBillrun,$noneMonthlyConfig);
			$nextActiveBillrunEndTime = Billrun_Billingcycle::getEndTime($nextActiveBillrun, $customerInvoicingDay,$noneMonthlyConfig);
		} else if($config->isMultiDayCycle() && !empty($customerInvoicingDay)) {
			$activeBillrun = Billrun_Billrun::getActiveBillrun($customerInvoicingDay); 
			$activeBillrunEndTime = Billrun_Billingcycle::getEndTime($activeBillrun, $customerInvoicingDay);
			$nextActiveBillrun = Billrun_Billingcycle::getFollowingBillrunKey($activeBillrun);
			$nextActiveBillrunEndTime = Billrun_Billingcycle::getEndTime($nextActiveBillrun, $customerInvoicingDay);
		} else {
			$activeBillrun = $this->activeBillrun;
			$activeBillrunEndTime = $this->activeBillrunEndTime;
			$nextActiveBillrun = $this->activeBillrun;
			$nextActiveBillrunEndTime = $this->nextActiveBillrunEndTime;
		}		

		if ($pricingData && (!isset($this->row['retail_rate']) || $this->row['retail_rate'])) {
			$urt = $this->row['urt']->sec;
			if ($urt <= $activeBillrunEndTime) { // lines in current billing cycle
				$billrunKey = $activeBillrun;
			} else if ($urt <= $nextActiveBillrunEndTime) { // late lines
				$billrunKey = $nextActiveBillrun;
			} else { // future lines
				$billrunKey = ($config->isMultiDayCycle() && !empty($customerInvoicingDay)) ? Billrun_Billingcycle::getBillrunKeyByTimestamp($urt, $customerInvoicingDay) : Billrun_Billingcycle::getBillrunKeyByTimestamp($urt);
			}
			$pricingData['billrun'] = $billrunKey;
		}

		return $pricingData;
	}

	/**
	 * In case balance is in over charge, 
	 * adds a refund row to the balance.
	 * currently, there is no support for postpay rebalance
	 * 
	 * @param float $rebalanceUsagev amount of balance (usagev) to return to the balance
	 * @param float $realUsagev
	 * @param array $lineToRebalance
	 * @param array $originalRow
	 */
	protected function handleRebalanceRequired($rebalanceUsagev, $realUsagev, $lineToRebalance, $originalRow) {
		$usaget = $lineToRebalance['usaget'];
		$rate = Billrun_Factory::db()->ratesCollection()->getRef($lineToRebalance->get('arate', true));

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
			$balance_totals_key = $this->balance->getBalanceTotalsKey($lineToRebalance);
			$balance['balance.totals.' . $balance_totals_key . '.usagev'] += $rebalanceUsagev;

			if (isset($lineToRebalance['arategroup'])) { // handle groups
				$group = $lineToRebalance['arategroup'];
				$balance['balance.groups.' . $group . '.usagev'] += $rebalanceUsagev;
			}
		}

		$rebalanceData = $this->getRebalanceData($lineToRebalance, $rate, $rebalanceUsagev, $realUsagev, $usaget);

		// Update balance cost
		if ($balance) {
			$rebalanceAprice = ($lineToRebalance['aprice'] - $rebalanceData['aprice']);
			$balance['balance.cost'] -= $rebalanceAprice;
			$balance['balance.totals.' . $balance_totals_key . '.cost'] -= $rebalanceAprice;
			if (isset($lineToRebalance['arategroup'])) { // handle groups
				$group = $lineToRebalance['arategroup'];
				$balance['balance.groups.' . $group . '.cost'] -= $rebalanceAprice;
			}
			$balance->save();
		}

		$originalRow['usagev_offset'] += $rebalanceUsagev;

		$updateQuery = $this->getUpdateLineUpdateQuery($rebalanceData);

		// Update line in archive
		$lines_archive_coll = Billrun_Factory::db()->archiveCollection();
		$lines_archive_coll->update(array('_id' => $lineToRebalance->getId()->getMongoId()), $updateQuery);

		// Update line in Lines collection will be done by Unify calculator
		$sessionQuery = $this->getSessionIdQuery($lineToRebalance->getRawData());
		$findQuery = array_merge(array("sid" => $lineToRebalance['sid']), $sessionQuery);
		$lines_coll = Billrun_Factory::db()->linesCollection();
		$options = array('multiple' => true); // this option is added in case we have sharding key=stamp and the update cannot be done
		$lines_coll->update($findQuery, $updateQuery, $options);
	}

	/**
	 * see Billrun_Calculator_Row_Customerpricing::isRebalanceRequired
	 * currently, there is no support for postpay rebalance
	 * 
	 * @return boolean
	 */
	protected function isRebalanceRequired() {
		return false;
	}

}
