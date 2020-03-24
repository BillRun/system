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
	
	const PRECISION = 0.005;
	
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
	 * Table holding the values of the following cycle keys, by cycle key.
	 * @var array
	 */
	protected static $followingCycleKeysTable = array();
	
	/**
	 * Table holding the values of the previous cycle keys, by cycle key.
	 * @var array
	 */
	protected static $previousCycleKeysTable = array();
	
	/**
	 * Cycle statuses cache (by page size)
	 * @var array
	 */
	protected static $cycleStatuses = array();

	/**
	 * returns the end timestamp of the input billing period
	 * @param type $key
	 * @param type $invoicing_day - in multi day cycle mode, need to send the invoicing day, so the billrun's end time will be calculated respectively.
	 * @return type int
	 */
	public static function getEndTime($key, $invoicing_day = null) {
		// Create the table if not already initialized
		if(!self::$cycleEndTable) {
			self::$cycleEndTable = new Billrun_DataTypes_CachedChargingTimeTable();
		}
		$config = Billrun_Factory::config();
		return (!is_null($invoicing_day) && $config->isMultiDayCycle()) ? self::$cycleEndTable->get($key, $invoicing_day) : self::$cycleEndTable->get($key);
	}
	
	/**
	 * 
	 * @param string $key
	 * @param array / mongoloid entity $customer
	 * @return type int - returns the end of the billrun cycle, according to the customer's invoicing_day field.
	 */
	public static function getEndTimeByCustomer($key, $customer) {
		// Create the table if not already initialized
		if (!self::$cycleEndTable) {
			self::$cycleEndTable = new Billrun_DataTypes_CachedChargingTimeTable();
		}

		$config = Billrun_Factory::config();
		return (!is_null($customer['invoicing_day']) && $config->isMultiDayCycle()) ? self::$cycleEndTable->get($key, $customer['invoicing_day']) : self::$cycleEndTable->get($key);
	}

	/**
	 * returns the start timestamp of the input billing period
	 * @param type $key
	 * @param type $invoicing_day - in multi day cycle mode, need to send the invoicing day, so the billrun's start time will be calculated respectively.
	 * @return type int
	 */
	public static function getStartTime($key, $invoicing_day = null) {
		// Create the table if not already initialized
		if(!self::$cycleStartTable) {
			self::$cycleStartTable = new Billrun_DataTypes_CachedChargingTimeTable('-1 month');
		}
		
		$config = Billrun_Factory::config();
		return (!is_null($invoicing_day) && $config->isMultiDayCycle()) ? self::$cycleStartTable->get($key, $invoicing_day) : self::$cycleStartTable->get($key);
	}
	
	/**
	 * 
	 * @param string $key
	 * @param array / mongoloid entity $customer
	 * @return type int - returns the start of the billrun cycle, according to the customer's invoicing_day field.
	 */
	public static function getStartTimeByCustomer($key, $customer) {
		// Create the table if not already initialized
		if (!self::$cycleStartTable) {
			self::$cycleStartTable = new Billrun_DataTypes_CachedChargingTimeTable('-1 month');
		}

		$config = Billrun_Factory::config();
		return (!is_null($customer['invoicing_day']) && $config->isMultiDayCycle()) ? self::$cycleStartTable->get($key, $customer['invoicing_day']) : self::$cycleStartTable->get($key);
	}

	/**
	 * Return the date constructed from the current billrun key
	 * @return string
	 */
	public static function getDatetime($billrunKey) {
		$dayofmonth = Billrun_Factory::config()->getConfigValue('billrun.charging_day', 1);
		return $billrunKey . str_pad($dayofmonth, 2, '0', STR_PAD_LEFT) . "000000";
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
			$config = Billrun_Factory::config();
			$dayofmonth = $config->getConfigChargingDay();
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
		if(!empty(self::$followingCycleKeysTable[$key])) {
			return self::$followingCycleKeysTable[$key];
		}
		$datetime = $key . "01000000";
		$month_later = strtotime('+1 month', strtotime($datetime));
		$ret = date("Ym", $month_later);
		self::$followingCycleKeysTable[$key] = $ret;
		return $ret;
	}

	/**
	 * Get the previous billrun key
	 * @param string $key - Current key
	 * @return string The previous key
	 */
	public static function getPreviousBillrunKey($key) {
		if(!empty(self::$previousCycleKeysTable[$key])) {
			return self::$previousCycleKeysTable[$key];
		}
		$datetime = $key . "01000000";
		$month_before = strtotime('-1 month', strtotime($datetime));
		$ret = date("Ym", $month_before);
		self::$previousCycleKeysTable[$key] = $ret;
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
			return self::getFirstTheoreticalBillingCycle();
		}
		return $entry['billrun_key'];
	}

	/**
	 * Preparing database for billing cycle rerun. 
	 *
	 * @param string $billrunKey - Billrun key
	 * 
	 */
    public static function removeBeforeRerun($billrunKey) {
		$billingCycleCol = self::getBillingCycleColl();
		Billrun_Factory::log("Removing billing cycle records for " . $billrunKey, Zend_Log::DEBUG);
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
	protected static function hasCycleStarted($billrunKey, $size) {
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
		if (static::isBillingCycleOver($billingCycleCol, $billrunKey, $size, $zeroPages) && $numOfPages != 0 && $finishedPages == $numOfPages) {
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
	 * Returns billrun keys of confirmed cycles according to the billrun keys that are transferred,
	 * if isn't transferred returns all confirmed cycles in the db.
	 * @param array $billrunKeys - Billrun keys.
	 * 
	 * @return bool - returns the keys of confirmed cycles
	 * 
	 */
	public static function getConfirmedCycles($billrunKeys = array()) {
		$billrunColl = Billrun_Factory::db()->billrunCollection();	
		if (!empty($billrunKeys)) {
			$pipelines[] = array(
				'$match' => array(
					'billrun_key' => array('$in' => $billrunKeys),
				),
			);
		}
		
		$pipelines[] = array(
			'$project' => array(
				'billrun_key' => 1,
				'confirmed' => array('$cond' => array('if' => array('$eq' => array('$billed', 1)), 'then' => 1 , 'else' => 0)),
			),
		);
		
		$pipelines[] = array(
			'$group' => array(
				'_id' => '$billrun_key',
				'confirmed' => array(
					'$sum' => '$confirmed',
				),
				'total' => array(
					'$sum' => 1,
				),
			),
		);
		
		$pipelines[] = array(
			'$project' => array(
				'billrun_key' => '$_id',
				'confirmed' => 1,
				'total' => 1,
			),
		);

		$potentialConfirmed = array();
		$results = $billrunColl->aggregate($pipelines);
		$resetCycles = self::getResetCycles($billrunKeys);
		foreach ($results as $billrunDetails) {
			if ($billrunDetails['confirmed'] == $billrunDetails['total']) {
				$potentialConfirmed[] = $billrunDetails['billrun_key'];
			}
		}
		$flipped = array_flip($potentialConfirmed);
		foreach ($resetCycles as $billrunKey) {
			unset($flipped[$billrunKey]);
		}
		$confirmedCycles = array_flip($flipped);
		return $confirmedCycles;	
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
		if (isset(self::$cycleStatuses[$billrunKey][$size])) {
			return self::$cycleStatuses[$billrunKey][$size];
		}
		$cycleStatus = '';
		$currentBillrunKey = self::getBillrunKeyByTimestamp();
		if ($billrunKey == $currentBillrunKey) {
			$cycleStatus = 'current';
		} else if ($billrunKey > $currentBillrunKey) {
			$cycleStatus = 'future';
		}
		if (empty($cycleStatus) && (self::isToRerun($billrunKey))) {
			$cycleStatus = 'to_rerun';
		}
		$cycleEnded = self::hasCycleEnded($billrunKey, $size);
		$cycleRunning = self::isCycleRunning($billrunKey, $size);
		if (empty($cycleStatus) && $billrunKey < $currentBillrunKey && !$cycleEnded && !$cycleRunning) {
			$cycleStatus = 'to_run';
		}		
		if (empty($cycleStatus) && $cycleRunning) {
			$cycleStatus = 'running';
		}
		$cycleConfirmed = empty($cycleStatus) ? !empty(self::getConfirmedCycles(array($billrunKey))) : false;
		if (empty($cycleStatus) && !$cycleConfirmed && $cycleEnded) {
			$cycleStatus = 'finished';
		}
		if (empty($cycleStatus) && $cycleEnded && $cycleConfirmed) {
			$cycleStatus = 'confirmed';
		}
		self::$cycleStatuses[$billrunKey][$size] = $cycleStatus;
		return $cycleStatus;
	}
	
	public static function getBillingCycleColl() {
		if (is_null(self::$billingCycleCol)) {
			self::$billingCycleCol = Billrun_Factory::db()->billing_cycleCollection();
		}
		
		return self::$billingCycleCol;
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
			if (!empty(self::getConfirmedCycles(array($billrunKey)))) {
				return $billrunKey;
			}
			$date = strtotime(($i + 1) . ' months ago');
			$billrunKey = self::getBillrunKeyByTimestamp($date);
		}
		return self::getFirstTheoreticalBillingCycle();
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
		$monthBeforeRegistration = strtotime('- 1 month', $registrationDate->sec);
		$registrationBillrunKey = Billrun_Billingcycle::getBillrunKeyByTimestamp($monthBeforeRegistration);
		return max(array($registrationBillrunKey, $lastBillrunKey));
	}

	public static function getLastNonRerunnableCycle() {
		$query = array('billed' => 1, 'billrun_key' => array('$regex' => '^\d{6}$'));
		$sort = array("billrun_key" => -1);
		$entry = Billrun_Factory::db()->billrunCollection()->query($query)->cursor()->sort($sort)->limit(1)->current();
		if ($entry->isEmpty()) {
			return FALSE;
		}
		return $entry['billrun_key'];
	}

	
	public static function isBillingCycleOver($cycleCol, $stamp, $size, $zeroPages=1){
		if (empty($zeroPages) || !Billrun_Util::IsIntegerValue($zeroPages)) {
			$zeroPages = 1;
		}
		$cycleQuery = array('billrun_key' => $stamp, 'page_size' => $size, 'count' => 0);
		$cycleCount = $cycleCol->query($cycleQuery)->count();
		
		if ($cycleCount >= $zeroPages) {
			Billrun_Factory::log("Finished going over all the pages", Zend_Log::DEBUG);
			return true;
		}		
		return false;
	}

	/**
	 * Returns accounts ids who have a confirmed invoice for the given cycle
	 * @param string $billrunKey
	 * @return array
	 */
	public static function getConfirmedAccountIds($billrunKey) {
		$billrunColl = Billrun_Factory::db()->billrunCollection();
		$query = array(
			'billrun_key' => $billrunKey,
			'billed' => 1,
		);
		$fields = array(
			'aid' => 1,
		);
		$confirmedInvoices = $billrunColl->find($query, $fields);
		$aids = array_column(iterator_to_array($confirmedInvoices),'aid');
		return $aids;
	}
	
	/**
	 * True if finished cycle was reseted.
	 * @param string $billrunKey - Billrun key
	 * 
	 * @return bool - True if finished cycle was reseted.
	 * 
	 */
	public static function isToRerun($billrunKey) {
		$billrunColl = Billrun_Factory::db()->billrunCollection();
		$billingCycleCol = self::getBillingCycleColl();
		$query = array(
			'billrun_key' => $billrunKey
		);
		
		$billrunDoc = $billrunColl->query($query)->count();
		$cycleDoc = $billingCycleCol->query($query)->count();
		
		
		if ($billrunDoc > 0 && $cycleDoc <= 0) {
			return true;
		}
		return false;
	}
	
	/**
	 * Returns reset cycles from the transferred billrun keys.
	 * @param string $billrunKeys - Billrun keys.
	 * 
	 * @return array - reset billrun keys.
	 * 
	 */
	public static function getResetCycles($billrunKeys) {
		$billrunCount = array();
		$cycleCount = array();
		$billrunColl = Billrun_Factory::db()->billrunCollection();
		$billingCycleCol = self::getBillingCycleColl();
		if (empty($billrunKeys)) {
			return array();
		}
		
		$pipelines[] = array(
			'$match' => array(
				'billrun_key' => array('$in' => $billrunKeys),
			),
		);

		$pipelines[] = array(
			'$group' => array(
				'_id' => '$billrun_key',
			),
		);
		
		$pipelines[] = array(
			'$project' => array(
				'billrun_key' => '$_id',
			),
		);

		$billrunResults = $billrunColl->aggregate($pipelines);
		$billingCycleResults = $billingCycleCol->aggregate($pipelines);
		foreach ($billrunResults as $billrunDetails){
			$billrunData = $billrunDetails->getRawData();
			$billrunCount[] = $billrunData['billrun_key'];
		}
		foreach ($billingCycleResults as $cycleDetails){
			$cycleData = $cycleDetails->getRawData();
			$cycleCount[] = $cycleData['billrun_key'];
		}
		$resetCycles = array_diff($billrunCount, $cycleCount);
		return $resetCycles;
	}
	
	public static function getFirstTheoreticalBillingCycle() {
		return '197001';
	}
	
	/**
	 * method to get the first billing cycle that was started
	 * if no cycle has been started, returns NULL
	 * 
	 * @return string format YYYYmm
	 */
	public static function getFirstStartedBillingCycle() {
		$sort = array("billrun_key" => 1);
		$entry = Billrun_Factory::db()->billing_cycleCollection()->query()->cursor()->sort($sort)->limit(1)->current();
		if ($entry->isEmpty()) {
			return NULL;
		}
		return $entry['billrun_key'];
	}
	
	public static function getCycleTimeStatus($billrunKey) {
		$currentBillrunKey = self::getBillrunKeyByTimestamp();
		if ($billrunKey == $currentBillrunKey) {
			return 'present';
		}
		if ($billrunKey > $currentBillrunKey) {
			return 'future';
		}
	
		return 'past';
	}
        
        /**
         * Function gets aid, start + end time, as Unix Timestamp.. 
         * @param integer $aid
         * @param string $startTime
         * @param string $endTime
         * @return array of the wanted account's immediate invoices, in the time rang.
         */
        public static function getImmediateInvoicesInRange($aid, $startTime, $endTime) {
            $convertedStartTime = date('YmdHis', $startTime);
            $convertedEndTime = date('YmdHis', $endTime);
            $query = array(
                        'aid' => $aid,
                        'attributes.invoice_type' => array('$eq' => 'immediate'),
                        'billrun_key' => array('$gte' => $convertedStartTime, '$lt' => $convertedEndTime)

		);
            $sort = array(
			'billrun_key' => -1,
		); 
            $billruns = Billrun_Factory::db()->billrunCollection()->query($query)->cursor()->sort($sort);
            $billrunsArray = iterator_to_array($billruns, true);
            $invoicesArray = [];
            foreach($billrunsArray as $id => $entity){
                    $invoicesArray[] = $entity->getRawData();
            }
            return $invoicesArray;
        }
}
