<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Subscriber flat aggregator.
 *
 * @package  Aggregator
 * @since    5.1
 */
class Billrun_Aggregator_Subscriber_Flat extends Billrun_Aggregator_Subscriber_Base {
	/**
	 * create and save flat lines
	 * @param type $subscriber
	 * @param type $billrun_key
	 * @return array of inserted lines
	 */
	public function save(Billrun_Subscriber $subscriber, $billrunKey) {
		$flatEntries = $this->getData($billrunKey, $subscriber);
		// Failed to get flat entries.
		if(!$flatEntries) {
			return array();
		}
		try {
			$flatEntriesRaw = array_map(function($obj) {
				return $obj->getRawData();
			}, $flatEntries);
			$ret = $this->lines->batchInsert($flatEntriesRaw, array("w" => 1));
			if (empty($ret['ok']) || empty($ret['nInserted']) || $ret['nInserted'] != count($flatEntries)) {
				Billrun_Factory::log('Error when trying to insert ' . count($flatEntries) . ' flat entries for subscriber ' . $subscriber->sid . '. Details: ' . print_r($ret, 1), Zend_Log::ALERT);
			}
		} catch (Exception $e) {
			$this->handleException($e, $subscriber, $billrunKey, $flatEntries);
		}
		return $flatEntries;
	}
	
	/**
	 * Handle an exception in the save function.
	 * @param Exception $e
	 * @param type $subscriber
	 * @param type $billrunKey
	 * @param type $rawData
	 * @return boolean true if should log the failure.
	 */
	protected function handleException(Exception $e, $subscriber, $billrunKey, $rawData) {
		if(!parent::handleException($e, $subscriber, $billrunKey, array())) {
			return false;
		}
		
		Billrun_Factory::log("Problem inserting flat lines for subscriber " . $subscriber->sid . " for billrun " . $billrunKey . ". error message: " . $e->getMessage() . ". error code: " . $e->getCode(), Zend_Log::ALERT);
		Billrun_Util::logFailedCreditRow($rawData);
	}
	
	/**
	 * 
	 * @param string $billrunKey
	 * @param array $plans
	 * @return array
	 */
	protected function getData($billrunKey, $subscriber) {
		$startTime = Billrun_Billrun::getStartTime($billrunKey);
		$endTime = Billrun_Billrun::getEndTime($billrunKey);
		$flatEntries = array();
		$plans = $subscriber->getCurrentPlans();
		foreach ($plans as $planArr) {
			/* @var $plan Billrun_Plan */
			$plan = $planArr['plan'];
			$fromDate = $planArr['from'];
			$toDate = $planArr['to'];
			$planActivation = $planArr['plan_activation'];
			$planDeactivation = isset($planArr['plan_deactivation']) ? $planArr['plan_deactivation'] : NULL;
			$flatChargeEntries = array_merge($flatEntries, $this->getChargeFlatEntries($subscriber, $billrunKey, $plan, $startTime, $endTime, $fromDate, $toDate, $planActivation, $planDeactivation));
			$flatEntries = array_merge($flatChargeEntries, $this->getRefundFlatEntries($subscriber, $billrunKey, $plan, $startTime, $endTime, $fromDate, $toDate, $planActivation, $planDeactivation));
		}
		$nextPlan = $subscriber->getNextPlan();
		if ($nextPlan && $nextPlan->isUpfrontPayment() && date(Billrun_Base::base_dateformat, $endTime) == $subscriber->getNextPlanActivationDate()) {
			$charge = $nextPlan->getPrice($subscriber->getNextPlanActivationDate(), date(Billrun_Base::base_dateformat, $subscriber->time), date(Billrun_Base::base_dateformat, $endTime));
			$flatEntries[] = $this->getFlatEntry($subscriber, $billrunKey, $nextPlan, $planArr['from'], $charge);
		}
		return $flatEntries;
	}

	protected function getChargeFlatEntries($subscriber, $billrunKey, $plan, $billingStart, $billingEnd, $fromDate, $toDate, $planActivation, $planDeactivation = NULL) {
		if ($plan->isUpfrontPayment()) {
			if (empty($planDeactivation)) {
				$monthsDiff = Billrun_Plan::getMonthsDiff($planActivation, date(Billrun_Base::base_dateformat, $billingEnd - 1));
			} else {
				$monthsDiff = Billrun_Plan::getMonthsDiff($planActivation, $planDeactivation);
			}
			if (empty($planDeactivation)) {
				if ($plan->getPeriodicity() == 'month' || ($plan->getPeriodicity() == 'year' && (((floor($monthsDiff) % 12) + $monthsDiff - floor($monthsDiff)) <= 1))) {
					$monthlyFraction = 1;
				}
			} else if (strtotime($planActivation) > $billingStart) { // subscriber deactivates and should be charged for a partial month
				$monthlyFraction = Billrun_Plan::calcFractionOfMonth($billrunKey, $planActivation, $planDeactivation) / ($plan->getPeriodicity() == 'year' ? 12 : 1);
			} else if (floor(Billrun_Plan::getMonthsDiff($planActivation, $fromDate)) != floor(Billrun_Plan::getMonthsDiff($planActivation, $planDeactivation))) {
				if ($plan->getPeriodicity() == 'year' && (((floor($monthsDiff) % 12) + $monthsDiff - floor($monthsDiff)) <= 1)) {
					$monthlyFraction = ((floor($monthsDiff) % 12) + $monthsDiff - floor($monthsDiff)) / 12;
				} else if ($plan->getPeriodicity() == 'month') {
					$monthlyFraction = Billrun_Plan::getMonthsDiff($planActivation, $planDeactivation) - floor(Billrun_Plan::getMonthsDiff($planActivation, $planDeactivation));
				}
			}
			if (isset($monthlyFraction)) {
				$charge = $monthlyFraction * $plan->getPrice($planActivation, $fromDate, $toDate);
			}
		} else {
			if ($plan->getPeriodicity() == 'month') {
				$charge = $plan->getPrice($planActivation, $fromDate, $toDate);
			}
		}
		if (isset($charge)) {
			$flatEntries = array($this->getFlatEntry($subscriber, $billrunKey, $plan, $fromDate, $charge));
		} else {
			$flatEntries = array();
		}
		return $flatEntries;
	}

	protected function getRefundFlatEntries($subscriber, $billrunKey, $plan, $billingStart, $billingEnd, $fromDate, $toDate, $planActivation, $planDeactivation = NULL) {
		if ($plan->isUpfrontPayment()) {
			if (!empty($planDeactivation)) {
				if (strtotime($planActivation) <= $billingStart) { // get a refund for a cancelled plan paid upfront
					$lastUpfrontCharge = $plan->getPrice($planActivation, $fromDate, $toDate);
					if ($plan->getPeriodicity() == 'year') {
						$monthsDiff = Billrun_Plan::getMonthsDiff($planActivation, $planDeactivation);
						$refundFraction = 1 - ((floor($monthsDiff) % 12) + $monthsDiff - floor($monthsDiff));
					} else if ($plan->getPeriodicity() == 'month') {
						$refundFraction = 1 - Billrun_Plan::calcFractionOfMonth($billrunKey, $fromDate, $planDeactivation);
					}
					$charge = -$lastUpfrontCharge * $refundFraction;
				}
			}
		}
		if (isset($charge)) {
			$flatEntries = array($this->getFlatEntry($subscriber, $billrunKey, $plan, $fromDate, $charge));
		} else {
			$flatEntries = array();
		}
		return $flatEntries;
	}

	protected function getFlatEntry(Billrun_Subscriber $subscriber, $billrunKey, $plan, $start, $charge) {
		$startTimestamp = strtotime($start);
		$flatEntry = new Mongodloid_Entity(array(
			'aid' => $subscriber->aid,
			'sid' => $subscriber->sid,
			'source' => 'billrun',
			'billrun' => $billrunKey,
			'type' => 'flat',
			'usaget' => 'flat',
			'urt' => new MongoDate($startTimestamp),
			'aprice' => $charge,
			'plan' => $plan->getName(),
			'plan_ref' => $plan->createRef(),
			'process_time' => new MongoDate(),
		));
		$stamp = md5($subscriber->aid . '_' . $subscriber->sid . $plan->getName() . '_' . $start . $billrunKey);
		$flatEntry['stamp'] = $stamp;
		return $flatEntry;
	}
}
