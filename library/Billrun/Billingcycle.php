<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class represents the billing cycle.
 *
 * @package  DataTypes
 * @since    5.2
 * @todo Create unit tests for this module
 */
class Billrun_Billingcycle {
    
	/**
	 * Table holding the values of the charging end dates.
	 * @var Billrun_DataTypes_CachedChargingTimeTable
	 */
	protected static $cycleEndTable = null;
	
	/**
	 * Table holding the values of the charging start dates.
	 * @var Billrun_DataTypes_CachedChargingTimeTable
	 */
	protected static $cycleStartTable = null;
        
	/**
	 * returns the end timestamp of the input billing period
	 * @param type $key
	 * @return type int
	 */
	public static function getEndTime($key) {
		// Create the table if not already initialized
		if(!self::$cycleEndTable) {
			self::$cycleEndTable = new Billrun_DataTypes_CachedChargingTimeTable();
		}
		
		return self::$cycleEndTable->get($key);
	}

	/**
	 * returns the start timestamp of the input billing period
	 * @param type $key
	 * @return type int
	 */
	public static function getStartTime($key) {
		// Create the table if not already initialized
		if(!self::$cycleStartTable) {
			self::$cycleStartTable = new Billrun_DataTypes_CachedChargingTimeTable('-1 month');
		}

		return self::$cycleStartTable->get($key);
	}
	
	/**
	 * Return the date constructed from the current billrun key
	 * @return string
	 */
	protected static function getDatetime() {
		$dayofmonth = Billrun_Factory::config()->getConfigValue('billrun.charging_day', 1);
		return self::$billrunKey . str_pad($dayofmonth, 2, '0', STR_PAD_LEFT) . "000000";
	}
	
	/**
	 * method to receive billrun key by date
	 * 
	 * @param int $timestamp a unix timestamp, if set to null, use current time
	 * @param int $dayofmonth the day of the month require to get; if omitted return config value
	 * @return string date string of format YYYYmm
	 */
	public static function getBillrunKeyByTimestamp($timestamp=null, $dayofmonth = null) {
		if($timestamp === null) {
			$timestamp = time();
		}
		
		if (!$dayofmonth) {
			$dayofmonth = Billrun_Factory::config()->getConfigValue('billrun.charging_day', 1);
		}
		$format = "Ym";
		if (date("d", $timestamp) < $dayofmonth) {
			$key = date($format, $timestamp);
		} else {
			$key = date($format, strtotime('+1 day', strtotime('last day of this month', $timestamp)));
		}
		return $key;
	}

	/**
	 * returns the end timestamp of the input billing period
	 * @param date $date
	 */
	public static function getBillrunEndTimeByDate($date) {
		$dateTimestamp = strtotime($date);
		$billrunKey = self::getBillrunKeyByTimestamp($dateTimestamp);
		return self::getEndTime($billrunKey);
	}

	/**
	 * returns the start timestamp of the input billing period
	 * @param date $date
	 */
	public static function getBillrunStartTimeByDate($date) {
		$dateTimestamp = strtotime($date);
		$billrunKey = self::getBillrunKeyByTimestamp($dateTimestamp);
		return self::getStartTime($billrunKey);
	}
	
	/**
	 * Get the next billrun key
	 * @param string $key - Current key
	 * @return string The following key
	 */
	public static function getFollowingBillrunKey($key) {
		$datetime = $key . "01000000";
		$month_later = strtotime('+1 month', strtotime($datetime));
		$ret = date("Ym", $month_later);
		return $ret;
	}

	/**
	 * Get the previous billrun key
	 * @param string $key - Current key
	 * @return string The previous key
	 */
	public static function getPreviousBillrunKey($key) {
		$datetime = $key . "01000000";
		$month_before = strtotime('-1 month', strtotime($datetime));
		$ret = date("Ym", $month_before);
		return $ret;
	}
	
	/**
	 * method to get the last closed billing cycle
	 * if no cycle exists will return 197001 (equivalent to unix timestamp)
	 * 
	 * @return string format YYYYmm
	 */
	public static function getLastClosedBillingCycle() {
		$sort = array("billrun_key" => -1);
		$entry = Billrun_Factory::db()->billing_cycleCollection()->query(array())->cursor()->sort($sort)->limit(1)->current();
		if ($entry->isEmpty()) {
			return '197001';
		}
		return $entry['billrun_key'];
	}

	/**
	 * Preparing database for billing cycle rerun. 
	 * @param $billingCycleCol - billing cycle collection
	 * @param string $billrunKey - Billrun key
	 * 
	 */
    public static function removeBeforeRerun($billingCycleCol, $billrunKey) {
		$billrunColl = Billrun_Factory::db()->billrunCollection();
		$billrunQuery = array('billrun_key' => $billrunKey, 'billed' => array('$ne' => 1));
		$countersColl = Billrun_Factory::db()->countersCollection();
		$billrunsToRemove = $billrunColl->query($billrunQuery)->cursor();
		foreach ($billrunsToRemove as $billrun) {
			$invoicesToRemove[] = $billrun['invoice_id'];
			if (count($invoicesToRemove) > 1000) {  // remove bulks from billrun collection(bulks of 1000 records)
				$countersColl->remove(array('coll' => 'billrun', 'seq' => array('$in' => $invoicesToRemove)));
				$invoicesToRemove = array();
			}
		}
		if (count($invoicesToRemove) > 0) { // remove leftovers
			$countersColl->remove(array('coll' => 'billrun', 'seq' => array('$in' => $invoicesToRemove)));
		}
		$billingCycleCol->remove(array('billrun_key' => $billrunKey));
		Billrun_Aggregator_Customer::removeBeforeAggregate($billrunKey);
	}

	
	/**
	 * True if billing cycle had started. 
	 * @param $billingCycleCol - billing cycle collection
	 * @param string $billrunKey - Billrun key
	 * @param int $size - size of page 
	 * 
	 * @return bool - True if billing cycle had started.
	 */
	protected function hasCycleStarted($billingCycleCol, $billrunKey, $size) {
		$existsKeyQuery = array('billrun_key' => $billrunKey, 'page_size' => $size);
		$keyCount = $billingCycleCol->query($existsKeyQuery)->count();
		if ($keyCount < 1) {
			return false;
		}
		return true;
	}

	/**
	 * True if billing cycle is ended. 
	 * @param $billingCycleCol - billing cycle collection
	 * @param string $billrunKey - Billrun key
	 * @param int $size - size of page 
	 * 
	 * @return bool - True if billing cycle is ended.
	 */
	public static function hasCycleEnded($billingCycleCol, $billrunKey, $size) {
		$zeroPages = Billrun_Factory::config()->getConfigValue('customer.aggregator.zero_pages_limit');
		$numOfPages = $billingCycleCol->query(array('billrun_key' => $billrunKey, 'page_size' => $size))->count();
		$finishedPages = $billingCycleCol->query(array('billrun_key' => $billrunKey, 'page_size' => $size, 'end_time' => array('$exists' => 1)))->count();
		if (Billrun_Aggregator_Customer::isBillingCycleOver($billingCycleCol, $billrunKey, $size, $zeroPages) && $numOfPages != 0 && $finishedPages == $numOfPages) {
			return true;
		}
		return false;
	}

	/**
	 * True if billing cycle is running for a given billrun key. 
	 * @param $billingCycleCol - billing cycle collection
	 * @param string $billrunKey - Billrun key
	 * @param int $size - size of page 
	 * 
	 * @return bool - True if generated all the bills from billrun objects
	 */
	public static function isCycleRunning($billingCycleCol, $billrunKey, $size) {
		if (!self::hasCycleStarted($billingCycleCol, $billrunKey, $size)) {
			return false;
		}
		if (self::hasCycleEnded($billingCycleCol, $billrunKey, $size)) {
			return false;
		}
		return true;
	}
	
	/**
	 * True if generated all the bills from billrun objects.
	 * @param string $billrunKey - Billrun key
	 * 
	 * @return bool - True if generated all the bills from billrun objects
	 * 
	 */
	public static function isCycleConfirmed($billrunKey) {
		$billrunColl = Billrun_Factory::db()->billrunCollection();
		$totalQuery = array(
			'billrun_key' => $billrunKey
		);
		$finishedQuery = array(
			'billrun_key' => $billrunKey,
			'billed' => 1
		);
		$totalBillrun = $billrunColl->query($totalQuery)->count();
		$numberOfFinished = $billrunColl->query($finishedQuery)->count();
		if ($numberOfFinished == $totalBillrun) {
			return true;
		}
		return false;
	}

	/**
	 * Returns the percentage of cycle progress. 
	 * @param $billingCycleCol - billing cycle collection
	 * @param string $billrunKey - Billrun key
	 * @param int $size - size of page 
	 * 
	 *  @return cycle completion percentage 
	 */
	public static function getCycleCompletionPercentage($billingCycleCol, $billrunKey, $size) {
		$totalPagesQuery = array(
			'billrun_key' => $billrunKey
		);
		$totalPages = $billingCycleCol->query($totalPagesQuery)->count();
		$finishedPagesQuery = array(
			'billrun_key' => $billrunKey,
			'end_time' => array('$exists' => true)
		);
		$finishedPages = $billingCycleCol->query($finishedPagesQuery)->count();
		if (self::hasCycleEnded($billingCycleCol, $billrunKey, $size)) {
			$completionPercentage = round(($finishedPages / $totalPages) * 100, 2);
		} else {
			$completionPercentage = round(($finishedPages / ($totalPages + 1)) * 100, 2);
		}

		return $completionPercentage;
	}
	
	/**
	 * Returns the number of generated bills.
	 * @param string $billrunKey - Billrun key
	 *
	 * @return int - number of generated bills.
	 */
	public static function getNumberOfGeneratedBills($billrunKey) {
		$billrunColl = Billrun_Factory::db()->billrunCollection();
		$query = array(
			'billrun_key' => $billrunKey,
			'billed' => 1
		);
		$generatedBills = $billrunColl->query($query)->count();
		return $generatedBills;
	}
	
	/**
	 * Returns the number of generated Invoices.
	 * @param string $billrunKey - Billrun key
	 * 
	 * @return int - number of generated Invoices.
	 */
	public static function getNumberOfGeneratedInvoices($billrunKey) {
		$billrunColl = Billrun_Factory::db()->billrunCollection();
		$query = array(
			'billrun_key' => $billrunKey
		);
		$generatedInvoices = $billrunColl->query($query)->count();
		return $generatedInvoices;
	}
		
	/**
	 * Computes the percentage of generated bills from billrun object.
	 * @param string $billrunKey - Billrun key
	 * @return percentage of completed bills
	 */
	public static function getCycleConfirmationPercentage($billrunKey) {
		$generatedInvoices = self::getNumberOfGeneratedInvoices($billrunKey);
		if ($generatedInvoices != 0) {
			return round((self::getNumberOfGeneratedBills($billrunKey) / $generatedInvoices) * 100, 2);
		}
		return 0;
	}
	
	/**
	 * Gets the newest confirmed billrun key
	 * 
	 * @return billrun key or 197001  if a confirmed cycle was not found
	 */
	public static function getLastConfirmedBillingCycle() {
		$maxIterations = 12;
		$billrunKey = self::getLastClosedBillingCycle();
		for ($i = 0; $i < $maxIterations; $i++) { // To avoid infinite loop
			if (self::isCycleConfirmed($billrunKey)) {
				return $billrunKey;
			}
			$date = strtotime(($i + 1) . ' months ago');
			$billrunKey = self::getBillrunKeyByTimestamp($date);
		}
		return '197001';
	}
	
	/**
	 * Gets the oldest available billrun key (tenant creation or key from time received)
	 * 
	 * @param $startTime - string time to create billrun key from
	 * @return billrun key
	 */
	public static function getOldestBillrunKey($startTime) {
		$lastBillrunKey = Billrun_Billingcycle::getBillrunKeyByTimestamp($startTime);
		$registrationDate = Billrun_Factory::config()->getConfigValue('registration_date');
		if (!$registrationDate) {
			return $lastBillrunKey;
		}
		$registrationBillrunKey = Billrun_Billingcycle::getBillrunKeyByTimestamp($registrationDate->sec);
		return max(array($registrationBillrunKey, $lastBillrunKey));
	}
}
