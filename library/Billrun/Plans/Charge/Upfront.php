<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Calculates an upfront charge
 *
 * @package  Plans
 * @since    5.2
 */
abstract class Billrun_Plans_Charge_Upfront extends Billrun_Plans_Charge_Base {

	/**
	 * The raw charge data the instance was constructed with (used by the reconciliation)
	 * @var array
	 */
	protected $planData = [];

	/**
	 * Did a billing cycle run (produce invoices), by billrun key (reconciliation cache)
	 * @var array
	 */
	protected static $ranCyclesCache = [];

	/**
	 * Plans that already had a reconciler in this run, by aid/sid/plan/billrun key.
	 * A plan can have several records in a cycle (deactivated and re-activated) - only one of them
	 * reconciles the previous run charge (the records are processed by their end date, so the
	 * record that spans the cycle start - if any - claims it first)
	 * @var array
	 */
	protected static $reconciledPlans = [];

	/**
	 * The expected (arrears) charge rows of the reconciled plans, by billrun key/aid/sid/plan -
	 * also the base of the upfront discount reconciliation, which calculates the discounts these
	 * rows deserve (BRCD-5421, see Billrun_DiscountManager::generateUpfrontDiscountRefundCdrs)
	 * @var array
	 */
	protected static $expectedCharges = [];

	/**
	 * The previous cycle run upfront lines of the account being processed, loaded in a single query
	 * by loadPreviousUpfrontLines and sliced by its callers (per plan, subscriber and credit kind).
	 * Holds one account at a time (keyed by aid/previous billrun key) - the cycle processes
	 * accounts sequentially
	 * @var array
	 */
	protected static $previousUpfrontLinesCache = [];

	public function __construct($plan) {
		parent::__construct($plan);
		$this->planData = $plan;
	}

	/**
	 * Reset the reconciliation static caches. The caches are only valid for a single billing cycle
	 * run - tests running several cycles in one process (or re-seeding the billing_cycle data)
	 * should reset them before each run.
	 */
	public static function resetReconciliationCache() {
		self::$reconciledPlans = [];
		self::$ranCyclesCache = [];
		self::$expectedCharges = [];
		self::$previousUpfrontLinesCache = [];
	}

	/**
	 * The expected (arrears) charge rows that the plan reconciliation calculated for an account,
	 * by sid and plan (BRCD-5421 - the base of the upfront discount reconciliation)
	 * @param int $aid
	 * @param string $billrunKey the reconciled cycle key
	 * @return array
	 */
	public static function getExpectedCharges($aid, $billrunKey) {
		return Billrun_Util::getIn(self::$expectedCharges, [$billrunKey, $aid], []);
	}

	/**
	 * Clear the expected charge rows of an account, once its reconciliation consumed them
	 * @param int $aid
	 * @param string $billrunKey the reconciled cycle key
	 */
	public static function clearExpectedCharges($aid, $billrunKey) {
		unset(self::$expectedCharges[$billrunKey][$aid]);
	}

	/**
	 * BRCD-5421 - instead of a classic refund, reconcile the charge that was made upfront by the
	 * previous cycle run for the current cycle, with the charge that should have been made given
	 * the currently known data:
	 * - both exist: charge/credit the difference (nothing when identical)
	 * - only the newly calculated charge exists: charge it as is
	 * - only the old charge exists: credit it fully
	 * @return array|null the reconciliation charge rows (each expected charge row is compared with
	 * its matching previous run line), null when there is nothing to reconcile
	 */
	public function getRefund(Billrun_DataTypes_CycleTime $cycle, $quantity=1) {
		$aid = Billrun_Util::getIn($this->planData, ['line_stump', 'aid'], null);
		$sid = Billrun_Util::getIn($this->planData, ['line_stump', 'sid'], null);
		$planName = Billrun_Util::getFieldVal($this->planData['plan'], Billrun_Util::getFieldVal($this->planData['name'], ''));
		if (!empty($this->planData['recurrence']['frequency']) && $this->planData['recurrence']['frequency'] > 1) {
			// custom recurrence (billing frequency) reconciliation is not supported yet (a
			// separate task) - skip it entirely. the previous run lines of such a plan live
			// under its last charging cycle key, NOT under the previous cycle key, so
			// reconciling here would find nothing and re-charge the whole (already paid) period
			// as missed on every charging run (double billing - see
			// testCustomRecurrencePlanIsNotReconciled)
			Billrun_Factory::log("Upfront reconciliation skipped for custom recurrence plan {$planName} (aid: {$aid}, sid: {$sid}, cycle: {$this->cycle->key()}) - billing frequency reconciliation is not supported yet", Zend_Log::NOTICE);
			return null;
		}
		$reconcileKey = $aid . '/' . $sid . '/' . $planName . '/' . $this->cycle->key();
		$prevKey = Billrun_Billingcycle::getPreviousBillrunKey($this->cycle->key());
		if (!self::shouldReconcile($prevKey, $this->cycle->invoicingDay())) {
			// the previous billing cycle never ran - the current cycle was never charged upfront so
			// there is nothing to reconcile (can be disabled by setting the
			// billrun.upfront.reconcile_requires_previous_billrun configuration to false)
			return null;
		}
		// the previous run lines are reconciled once per plan - when the plan has several records in
		// the cycle (e.g. deactivated and re-activated), the others reconcile their expected charge
		// against nothing, charging it as is
		$olds = [];
		if (!isset(self::$reconciledPlans[$reconcileKey])) {
			self::$reconciledPlans[$reconcileKey] = true;
			$oldLines = self::loadPreviousUpfrontLines($aid, $prevKey, 'flat', $sid);
			$olds = Billrun_Util::getFieldVal($oldLines[$planName], []);
		}
		$expected = $this->getExpectedUpfrontCharge($quantity);
		foreach ($expected as $expectedRow) {
			// the expected rows also deserve the current cycle discounts - kept for the upfront
			// discount reconciliation
			self::$expectedCharges[$this->cycle->key()][$aid][$sid][$planName][] = $expectedRow;
		}
		$rows = $this->reconcile($expected, $olds);
		return empty($rows) ? null : $rows;
	}

	/**
	 * BRCD-5421 - the discount (credit) counterpart of getRefund: reconcile the upfront discount
	 * lines that the previous cycle run created for the current cycle with the discount deserved by
	 * the currently known data, using the same reconciliation logic and cases as the plan charges.
	 * The rows are in the line price space - a given discount is a negative price.
	 *
	 * @param array $expected the deserved discount rows ('value' - the negative discount price),
	 * empty when the discount is not deserved at all
	 * @param array $olds the previous run upfront discount lines of the discount and subscriber
	 * @return array the reconciliation rows
	 */
	public function getDiscountRefund($expected, $olds) {
		return $this->reconcile($expected, $olds, 'discount_start', 'discount_end');
	}

	/**
	 * BRCD-5421 - the reconciliation core, shared by the plan (flat) and discount (credit) upfront
	 * lines:
	 * - both exist: charge/credit the difference (nothing when identical), covering the period
	 *   shared by the two rows - the latest start and the earliest end (a missing field means the
	 *   cycle own boundary)
	 * - only the newly calculated row exists: create it as is
	 * - only the old line exists: charge it back fully
	 * @param array $expected the newly calculated rows ('value' - the line price)
	 * @param array $olds the previous cycle run lines ('aprice' - the line price)
	 * @param string $startField the period start field (start for plans, discount_start for discounts)
	 * @param string $endField the period end field (end for plans, discount_end for discounts)
	 * @return array the reconciliation rows
	 */
	protected function reconcile($expected, $olds, $startField = 'start', $endField = 'end') {
		$rows = [];
		foreach ($expected as $expectedRow) {
			// each expected row is compared with its exact previous run line - the reconciliation
			// row is the expected row with the difference as its price
			$old = $this->extractMatchingOldLine($olds, $expectedRow);
			$diff = ($expectedRow['value'] ?? 0) - (is_null($old) ? 0 : ($old['aprice'] ?? 0));
			if (Billrun_Util::isEqual($diff, 0, 0.000001)) {
				continue;
			}
			$expectedRow['value'] = $diff;
			$expectedRow['is_upfront'] = false;
			if (!is_null($old)) {
				// the reconciliation row covers the difference between the old line and the
				// expected row periods - the period whose charge is corrected (a missing field
				// means the cycle own boundary)
				$expectedInterval = [[
					'from' => !empty($expectedRow[$startField]) ? Billrun_Utils_Time::getTime($expectedRow[$startField]) : $this->cycle->start(),
					'to' => !empty($expectedRow[$endField]) ? Billrun_Utils_Time::getTime($expectedRow[$endField]) : $this->cycle->end(),
				]];
				$oldInterval = [[
					'from' => !empty($old[$startField]) ? Billrun_Utils_Time::getTime($old[$startField]) : $this->cycle->start(),
					'to' => !empty($old[$endField]) ? Billrun_Utils_Time::getTime($old[$endField]) : $this->cycle->end(),
				]];
				$difference = Billrun_Utils_Time::getIntervalsDifference($oldInterval, $expectedInterval);
				if (empty($difference)) {
					$difference = Billrun_Utils_Time::getIntervalsDifference($expectedInterval, $oldInterval);
				}
				if (!empty($difference)) {
					$asDate = Billrun_Util::getFieldVal($expectedRow[$startField], Billrun_Util::getFieldVal($old[$startField], null)) instanceof Mongodloid_Date;
					$correctedPeriod = reset($difference);
					$expectedRow[$startField] = $asDate ? new Mongodloid_Date($correctedPeriod['from']) : $correctedPeriod['from'];
					$expectedRow[$endField] = $asDate ? new Mongodloid_Date($correctedPeriod['to']) : $correctedPeriod['to'];
				}
			}
			$rows[] = $expectedRow;
		}
		// $olds is left with the unmatched lines only (extractMatchingOldLine removes each matched
		// one) - lines that should not have been created at all are charged back fully, as created
		foreach ($olds as $old) {
			$row = $this->getOldLineChargebackRow($old);
			if (!Billrun_Util::isEqual($row['value'], 0, 0.000001)) {
				$rows[] = $row;
			}
		}
		return $rows;
	}

	/**
	 * Extract the old line that matches an expected charge row - an old line of the same charged
	 * entity (plan / discount), by its price tier when both carry it, otherwise the first such
	 * line left. The matched line is removed from the given list.
	 * @param array $olds the still unmatched old lines
	 * @param array $expectedRow the expected charge row
	 * @return array|null null when no matching old line is left
	 */
	protected function extractMatchingOldLine(&$olds, $expectedRow) {
		foreach ($olds as $key => $old) {
			if ($this->isSameChargedEntity($expectedRow, $old) &&
					isset($expectedRow['cycle'], $old['cycle']) && $expectedRow['cycle'] == $old['cycle']) {
				unset($olds[$key]);
				return $old;
			}
		}
		foreach ($olds as $key => $old) {
			if ($this->isSameChargedEntity($expectedRow, $old)) {
				unset($olds[$key]);
				return $old;
			}
		}
		return null;
	}

	/**
	 * Verify an old line charges the same entity the expected row settles - the same plan this
	 * charge reconciles (flat lines) or the same discount (credit lines, by the discount key the
	 * expected discount CDR carries).
	 * @param array $expectedRow the expected charge row
	 * @param array $old an old upfront line
	 * @return boolean
	 */
	protected function isSameChargedEntity($expectedRow, $old) {
		if (Billrun_Util::getFieldVal($old['type'], '') == 'credit') {
			$expectedKey = Billrun_Util::getFieldVal($expectedRow['key'], Billrun_Util::getFieldVal($expectedRow['arate_key'], ''));
			$oldKey = Billrun_Util::getFieldVal($old['key'], Billrun_Util::getFieldVal($old['arate_key'], ''));
			return $expectedKey == $oldKey;
		}
		$planName = Billrun_Util::getFieldVal($this->planData['plan'], Billrun_Util::getFieldVal($this->planData['name'], ''));
		return Billrun_Util::getFieldVal($old['plan'], '') == $planName;
	}

	/**
	 * A reconciliation charge row crediting an old upfront line back fully, as it was charged:
	 * the old line is the charge row with an inverted price. The line specific fields (price,
	 * taxation, upfront marking) are removed - the aggregation calculates them again for the
	 * reconciliation line.
	 * @param array $old the old upfront line
	 * @return array the charge row
	 */
	protected function getOldLineChargebackRow($old) {
		$row = $old;
		$row['value'] = -($old['aprice'] ?? 0);
		$row['full_price'] = floatval(Billrun_Util::getFieldVal($row['full_price'], $row['aprice']));
		$row['start'] = !empty($row['start']) ? Billrun_Utils_Time::getTime($row['start']) : $this->cycle->start();
		$row['end'] = !empty($row['end']) ? Billrun_Utils_Time::getTime($row['end']) : $this->cycle->end();
		$row['is_upfront'] = false;
		unset($row['_id'], $row['aprice'], $row['split'], $row['final_charge']);
		return $row;
	}
	
	/**
	 * Get the price of the current plan - the upfront charge is the regular (arrears) charge of
	 * the next cycle, only paid in advance, so it is calculated by the matching arrears charge
	 * class, taking the changes that are already known into account (BRCD-5421). the current cycle
	 * itself is settled by the reconciliation (getRefund).
	 * @return array|null the upfront charge rows, null if no charge
	 */
	public function getPrice($quantity = 1) {
		$nextCycle = self::getUpfrontCycle($this->cycle, Billrun_Util::getFieldVal($this->planData['recurrence'], null), $this->activation);
		// the full fraction mode charges the full next cycle, ignoring the changes that are already
		// known (the legacy upfront behavior, e.g. for tests emulating a legacy previous run)
		$fullFraction = Billrun_Factory::config()->getConfigValue('billrun.upfront.full_fraction', false);
		// Is the activation beyond the charged (next) cycle, or the deactivation before the
		// current one? (an activation within the next cycle is known in advance and charged
		// prorated - BRCD-5421 - except in the full fraction mode, which does not know the future)
		if( $this->activation > ($fullFraction ? $this->cycle->end() : $nextCycle->end()) || $this->deactivation < $this->cycle->start()) {
			return null;
		}
		if (!empty($this->deactivation) && $this->deactivation <= $nextCycle->start()) {
			// the plan is already known to end before the next cycle starts - nothing to pay
			// upfront. this holds in the full fraction mode as well - legacy never paid the next
			// cycle for a plan not active at its start
			return null;
		}
		$arrearsCharge = $this->getArrearsCharge($nextCycle, $fullFraction);
		if (is_null($arrearsCharge)) {
			Billrun_Factory::log('No matching arrears charge calculator for ' . get_class($this), Zend_Log::ERR);
			return null;
		}
		$charges = [];
		foreach (($arrearsCharge->getPrice($quantity) ?: []) as $row) {
			$row['is_upfront'] = true;
			// the upfront line carries the charged (next cycle) period in start/end - the arrears
			// rows carry the price tier dates there (or leave them empty, e.g. an UNLIMITED tier
			// end), and the tier dates are already kept in start_date/end_date. a start/end within
			// the cycle (a mid cycle activation/deactivation known in advance) is kept
			if (empty($row['start']) || $row['start'] < $nextCycle->start()) {
				$row['start'] = $nextCycle->start();
			}
			if (empty($row['end']) || $row['end'] > $nextCycle->end()) {
				$row['end'] = $nextCycle->end();
			} else if ($row['end'] < $nextCycle->end()) {
				// the arrears rows end a second before the next period - the line period uses
				// exclusive edges (the deactivation moment itself)
				$row['end'] += 1;
			}
			$charges[] = $row;
		}
		return empty($charges) ? null : $charges;
	}

	/**
	 * The matching arrears charge calculator, for a given cycle.
	 * @return Billrun_Plans_Charge_Base|null null when there is no matching arrears class
	 */
	protected function getArrearsCharge(Billrun_DataTypes_CycleTime $cycle, $fullCycle = false) {
		$arrearsClass = str_replace('_Upfront', '_Arrears', get_class($this));
		if (!class_exists($arrearsClass)) {
			return null;
		}
		$data = $this->planData;
		$data['cycle'] = $cycle;
		if ($fullCycle) {
			// the known end is ignored - the full cycle is charged
			unset($data['end'], $data['deactivation_date'], $data['plan_deactivation']);
		}
		return new $arrearsClass($data);
	}

	/**
	 * The charge that the *current* cycle should carry for this plan record, given the currently
	 * known data (BRCD-5421 reconciliation) - the regular (arrears) charge of the current cycle,
	 * calculated by the matching arrears charge class. This includes periods the previous run
	 * could not know, e.g. a mid cycle activation.
	 * @param int $quantity
	 * @return array charge rows, empty when the plan was not active during the cycle
	 */
	public function getExpectedUpfrontCharge($quantity = 1) {
		if ($this->activation >= $this->cycle->end()) {
			// not active during the reconciled cycle yet
			return [];
		}
		if (!empty($this->deactivation) && $this->deactivation <= $this->cycle->start()) {
			// was not active during the reconciled cycle at all
			return [];
		}
		$arrearsCharge = $this->getArrearsCharge($this->cycle);
		if (is_null($arrearsCharge)) {
			Billrun_Factory::log('No matching arrears charge calculator for ' . get_class($this), Zend_Log::ERR);
			return [];
		}
		$charges = $arrearsCharge->getPrice($quantity) ?: [];
		foreach ($charges as &$row) {
			if (!empty($row['end']) && $row['end'] < $this->cycle->end()) {
				// the arrears rows end a second before the next period - the reconciliation and
				// the line period use exclusive edges (the deactivation moment itself)
				$row['end'] += 1;
			}
		}
		unset($row);
		return $charges;
	}

	//======================== BRCD-5421 - upfront reconciliation =========================
	// The upfront charges/discounts of the previous cycle run are compared with the ones that
	// should have been made given the currently known data:
	// - both exist: charge/credit the difference (nothing when identical)
	// - only the newly calculated one exists: create it as is
	// - only the old one exists: credit/charge it back fully

	/**
	 * Should the previous cycle charges be reconciled?
	 * By default, when the previous billing cycle never ran (e.g. the first cycle of the
	 * installation) there is nothing to reconcile against, and reconciling would charge the whole
	 * current cycle of every upfront plan as a missed charge.
	 * Setting the billrun.upfront.reconcile_requires_previous_billrun configuration to false
	 * disables the check, and the reconciliation runs even when the previous cycle never ran
	 * (charging/crediting whatever was missed).
	 * @param string $previousBillrunKey
	 * @param string|null $invoicingDay multi day cycle mode invoicing day
	 * @return boolean
	 */
	public static function shouldReconcile($previousBillrunKey, $invoicingDay = null) {
		if (!Billrun_Factory::config()->getConfigValue('billrun.upfront.reconcile_requires_previous_billrun', false)) {
			return true;
		}
		$cacheKey = $previousBillrunKey . ($invoicingDay ?: '');
		if (!isset(self::$ranCyclesCache[$cacheKey])) {
			$size = (int) Billrun_Factory::config()->getConfigValue('customer.aggregator.size', 100);
			self::$ranCyclesCache[$cacheKey] = Billrun_Billingcycle::hasCycleEnded($previousBillrunKey, $size, $invoicingDay);
		}
		return self::$ranCyclesCache[$cacheKey];
	}

	/**
	 * Load the upfront lines that were created by the previous cycle run (they relate to the current cycle).
	 * The whole account is fetched (and cached) in a single query - the repeated calls (per plan,
	 * subscriber and credit kind) only slice the cached result.
	 * @param int $aid
	 * @param string $previousBillrunKey
	 * @param string $type 'flat' - the upfront plan lines, grouped by plan name
	 * 					   'credit' - the upfront discount/charge lines, grouped by key and sid
	 * @param int|null $sid limit to a single subscriber (null - the whole account)
	 * @param string $usaget the credit lines kind - 'discount' / 'conditional_charge'
	 * @return array
	 */
	public static function loadPreviousUpfrontLines($aid, $previousBillrunKey, $type = 'flat', $sid = null, $usaget = 'discount') {
		$cacheKey = $aid . '/' . $previousBillrunKey;
		if (!isset(self::$previousUpfrontLinesCache[$cacheKey])) {
			self::$previousUpfrontLinesCache = [$cacheKey => self::loadAccountUpfrontLines($aid, $previousBillrunKey)];
		}
		$cached = self::$previousUpfrontLinesCache[$cacheKey];
		if ($type == 'credit') {
			return Billrun_Util::getIn($cached, ['credit', $usaget], []);
		}
		if (!is_null($sid)) {
			return Billrun_Util::getIn($cached, ['flat', $sid], []);
		}
		// the whole account - merge the per subscriber plan groups
		$ret = [];
		foreach ($cached['flat'] as $sidLines) {
			foreach ($sidLines as $planName => $lines) {
				$ret[$planName] = array_merge(Billrun_Util::getFieldVal($ret[$planName], []), $lines);
			}
		}
		return $ret;
	}

	/**
	 * The single per-account fetch behind loadPreviousUpfrontLines - all the upfront lines the
	 * previous cycle run created for the current cycle, grouped for both reconciliations:
	 * 'flat' (plan) lines by subscriber and plan name, 'credit' lines by kind (usaget - the credit
	 * lines also include manual credits, the kind grouping keeps only the requested one out),
	 * key and subscriber.
	 * Covered by the {aid, billrun} partial lines index over {is_upfront: true, charge_op: 'charge'}.
	 * @param int $aid
	 * @param string $previousBillrunKey
	 * @return array
	 */
	protected static function loadAccountUpfrontLines($aid, $previousBillrunKey) {
		$query = array(
			'aid' => $aid,
			'billrun' => $previousBillrunKey,
			'type' => array('$in' => array('flat', 'credit')),
			'is_upfront' => true,
			'charge_op' => 'charge',
		);
		$ret = ['flat' => [], 'credit' => []];
		Billrun_Factory::log("Loading previous upfront lines for aid: {$aid}, cycle: {$previousBillrunKey}", Zend_Log::DEBUG);
		$cursor = Billrun_Factory::db()->linesCollection()->query($query)->cursor();
		foreach ($cursor as $line) {
			$raw = $line->getRawData();
			$lineSid = Billrun_Util::getFieldVal($raw['sid'], 0);
			if ($raw['type'] == 'credit') {
				$key = !empty($raw['key']) ? $raw['key'] : (!empty($raw['arate_key']) ? $raw['arate_key'] : Billrun_Util::getFieldVal($raw['name'], ''));
				if ($key === '') {
					continue;
				}
				$ret['credit'][Billrun_Util::getFieldVal($raw['usaget'], '')][$key][$lineSid][] = $raw;
			} else if (!empty($raw['plan'])) {
				$ret['flat'][$lineSid][$raw['plan']][] = $raw;
			}
		}
		return $ret;
	}

	//===================================================================================

	/**
	 * The next (upfront paid) cycle of a given cycle.
	 * @param Billrun_DataTypes_CycleTime $cycle
	 * @param array|null $recurrenceConfig a frequency based (custom) recurrence configuration
	 * @param int|null $activation the plan activation time (custom recurrence alignment)
	 * @return Billrun_DataTypes_CycleTime
	 */
	public static function getUpfrontCycle(Billrun_DataTypes_CycleTime $cycle, $recurrenceConfig = null, $activation = null) {
		$nextCycleKey = Billrun_Billingcycle::getFollowingBillrunKey($cycle->key());
		if (!empty($recurrenceConfig['frequency'])) {
			return new Billrun_DataTypes_CustomCycleTime($nextCycleKey, $recurrenceConfig, $cycle->invoicingDay(), $activation);
		}
		return new Billrun_DataTypes_CycleTime($nextCycleKey, $cycle->invoicingDay());
	}

}
