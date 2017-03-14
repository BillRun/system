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
        
    public function removeBeforeRerun($billingCycleCol, $billrunKey) {
		$linesColl = Billrun_Factory::db()->linesCollection();
		$billrunColl = Billrun_Factory::db()->billrunCollection();
		$linesRemoveQuery = array('type' => array('$in' => array('service', 'flat')));
		$billrunQuery = array('billrun_key' => $billrunKey, 'billed' => array('$ne' => 1));
		$countersColl = Billrun_Factory::db()->countersCollection();
		$billrunsToRemove = $billrunColl->query($billrunQuery)->cursor();
		foreach ($billrunsToRemove as $billrun) {
			$invoicesToRemove[] = $billrun['invoice_id'];
			if (count($invoicesToRemove) > 1000) {  // remove bulks from billrun collection(bulks of 1000 records)
				$countersColl->remove(array('seq' => array('$in' => $invoicesToRemove)));
				$invoicesToRemove = array();
			}
		}
		if (count($invoicesToRemove) > 0) { // remove leftovers
			$countersColl->remove(array('seq' => array('$in' => $invoicesToRemove)));
		}
		$billingCycleCol->remove(array('billrun_key' => $billrunKey));
		$linesColl->remove($linesRemoveQuery);
		$billrunColl->remove($billrunQuery);
	}

	public function isBillingCycleRerun($billingCycleCol, $billrunKey, $size) {
		$zeroPages = Billrun_Factory::config()->getConfigValue('customer.aggregator.zero_pages_limit');
		return Billrun_Aggregator_Customer::isBillingCycleOver($billingCycleCol, $billrunKey, $size, $zeroPages);
	}

	protected function hasCycleStarted($billingCycleCol, $billrunKey, $size) {
		$existsKeyQuery = array('billrun_key' => $billrunKey, 'page_size' => $size);
		$keyCount = $billingCycleCol->query($existsKeyQuery)->count();
		if ($keyCount < 1) {
			return false;
		}
		return true;
	}

	public function hasCycleEnded($billingCycleCol, $billrunKey, $size) {
		$zeroPages = Billrun_Factory::config()->getConfigValue('customer.aggregator.zero_pages_limit');
		if (Billrun_Aggregator_Customer::isBillingCycleOver($billingCycleCol, $billrunKey, $size, $zeroPages)) {
			return true;
		}
		return false;
	}

	public function isCycleRunning($billingCycleCol, $billrunKey, $size) {
		if (!self::hasCycleStarted($billingCycleCol, $billrunKey, $size)) {
			return false;
		}
		$zeroPages = Billrun_Factory::config()->getConfigValue('customer.aggregator.zero_pages_limit');
		if (Billrun_Aggregator_Customer::isBillingCycleOver($billingCycleCol, $billrunKey, $size, $zeroPages)) {
			return false;
		}
		return true;
	}

	public function isCycleConfirmed($billrunKey) {
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

	public function getCycleCompletionPercentage($billingCycleCol, $billrunKey, $size) {
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
			$completionPercentage = ($finishedPages / $totalPages) * 100;
		} else {
			$completionPercentage = ($finishedPages / ($totalPages + 1)) * 100;
		}

		return $completionPercentage;
	}

}
