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
	
	protected static $billingCycleCol = null;
    
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
    public static function removeBeforeRerun($billrunKey) {
		$billingCycleCol = self::getBillingCycleColl();
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
	protected function hasCycleStarted($billrunKey, $size) {
		$billingCycleCol = self::getBillingCycleColl();
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
	public static function hasCycleEnded($billrunKey, $size) {
		$billingCycleCol = self::getBillingCycleColl();
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
	public static function isCycleRunning($billrunKey, $size) {
		if (!self::hasCycleStarted($billrunKey, $size)) {
			return false;
		}
		if (self::hasCycleEnded($billrunKey, $size)) {
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
	public static function getCycleCompletionPercentage($billrunKey, $size) {
		$billingCycleCol = self::getBillingCycleColl();
		$totalPagesQuery = array(
			'billrun_key' => $billrunKey
		);
		$totalPages = $billingCycleCol->query($totalPagesQuery)->count();
		$finishedPagesQuery = array(
			'billrun_key' => $billrunKey,
			'end_time' => array('$exists' => true)
		);
		$finishedPages = $billingCycleCol->query($finishedPagesQuery)->count();
		if (self::hasCycleEnded($billrunKey, $size)) {
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
	
	public static function getCycleStatus($billrunKey, $size = null) {
		if (is_null($size)) {
			$size = (int) Billrun_Factory::config()->getConfigValue('customer.aggregator.size', 100);
		}
		$currentBillrunKey = self::getBillrunKeyByTimestamp();
		$cycleConfirmed = self::isCycleConfirmed($billrunKey);
		$cycleEnded = self::hasCycleEnded($billrunKey, $size);
		$cycleRunning = self::isCycleRunning($billrunKey, $size);
		
		if ($billrunKey == $currentBillrunKey) {
			return 'current';
		}
		if ($billrunKey > $currentBillrunKey) {
			return 'future';
		}
		if ($billrunKey < $currentBillrunKey && !$cycleEnded && !$cycleRunning) {
			return 'to_run';
		} 
		
		if ($cycleRunning) {
			return 'running';
		}
		
		if (!$cycleConfirmed && $cycleEnded) {
			return 'finished';
		}
		
		if ($cycleEnded && $cycleConfirmed) {
			return 'confirmed';
		}

		return '';
	}
	
	public static function getBillingCycleColl() {
		if (is_null(self::$billingCycleCol)) {
			self::$billingCycleCol = Billrun_Factory::db()->billing_cycleCollection();
		}
		
		return self::$billingCycleCol;
	}
}
